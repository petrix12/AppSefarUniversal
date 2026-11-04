"""Mirror a single BrightBean workspace to App Sefar and send its AI replies.

Runs inside the pinned BrightBean image. Its own SQLite cursor survives restarts;
all application writes and sends use the two applications' normal service paths.
"""

import datetime as dt
import fcntl
import logging
import os
from pathlib import Path
import signal
import sqlite3
import threading
import uuid

import httpx

LOG = logging.getLogger("sefar_bridge")
STOP = threading.Event()


class AppApiError(Exception):
    def __init__(self, status):
        self.status = int(status)
        super().__init__(f"App Sefar returned HTTP {self.status}")


def message_payload(message):
    contact = message.conversation.contact
    body = message.body if isinstance(message.body, dict) else {}
    text = "\n".join(
        block.get("text", "") for block in body.get("blocks", [])
        if isinstance(block, dict) and block.get("type") == "text" and isinstance(block.get("text"), str)
    )
    deleted = message.status == "deleted"
    return {
        "workspace_id": str(message.workspace_id),
        "external_id": str(message.pk),
        "conversation_id": str(message.conversation_id),
        "contact_id": str(contact.pk),
        "connection_id": str(message.channel_connection_id),
        "platform": message.channel_connection.platform,
        "direction": message.direction,
        "source": message.source,
        "status": message.status,
        "internal": message.internal,
        "text": None if deleted else text[:20000],
        "body": {} if deleted else body,
        "contact": {name: getattr(contact, name, "") for name in ("first_name", "last_name", "email", "phone")},
        "source_created_at": message.created_at.isoformat(),
        "source_updated_at": message.updated_at.isoformat(),
    }


def may_reply(message):
    """Never answer old imports, internal notes, closed threads or an agent."""
    from django.utils import timezone
    from apps.messaging.models import Conversation, Message

    message.refresh_from_db(
        from_queryset=Message.objects.for_workspace(message.workspace_id),
        fields=['status', 'internal', 'body'],
    )
    if message.direction != "in" or message.internal or message.status == "deleted":
        return False
    if message.created_at < timezone.now() - dt.timedelta(minutes=5):
        return False
    conversation = Conversation.objects.for_workspace(message.workspace_id).get(pk=message.conversation_id)
    if conversation.state != "open":
        return False
    if conversation.automation_paused_until and conversation.automation_paused_until > timezone.now():
        return False
    return not Message.objects.for_workspace(message.workspace_id).filter(
        conversation_id=message.conversation_id,
        direction="out", source="agent", internal=False, created_at__gte=message.created_at,
    ).exists()


def api_post(client, path, payload):
    # Do not log response bodies or exception strings: either may contain secrets.
    response = client.post(path, json=payload)
    if response.status_code >= 300:
        raise AppApiError(response.status_code)
    return response.json()


def sync_batch(client, state, messages):
    from apps.channels.events import OutboundMessage, TextBlock
    from apps.messaging.services import send_via_api

    result = api_post(client, "messages", {"messages": [message_payload(m) for m in messages]})
    identifiers = {str(row["external_id"]): row["id"] for row in result["data"]}
    for message in messages:
        if may_reply(message) and message_payload(message)["text"]:
            result = api_post(client, f"messages/{identifiers[str(message.pk)]}/reply", {})
            answer = result["data"]["reply"]
            # Re-check after the model call: an agent may have taken over meanwhile.
            if isinstance(answer, str) and answer.strip() and may_reply(message):
                conversation = message.conversation
                send_via_api(
                    workspace=conversation.workspace,
                    contact=conversation.contact,
                    connection=message.channel_connection,
                    outbound=OutboundMessage(blocks=(TextBlock(text=answer),)),
                    idempotency_key=result["data"]["idempotency_key"],
                )
        state.execute("INSERT OR REPLACE INTO seen (id, version) VALUES (?, ?)",
                      (str(message.pk), message.updated_at.isoformat()))
        state.commit()


def cycle(client, state, workspace_id):
    from apps.messaging.models import Message

    row = state.execute("SELECT value FROM metadata WHERE key='cursor'").fetchone()
    cursor = dt.datetime.fromisoformat(row[0]) if row else dt.datetime(1970, 1, 1, tzinfo=dt.timezone.utc)
    # Overlap catches rows committed shortly after a previous scan passed their timestamp.
    since = cursor - dt.timedelta(minutes=2)
    query = Message.objects.for_workspace(workspace_id).filter(updated_at__gte=since).select_related(
        "conversation__contact", "conversation__workspace", "channel_connection",
    ).order_by("updated_at", "pk")
    newest = cursor
    batch = []
    count = 0
    for message in query.iterator(chunk_size=50):
        newest = max(newest, message.updated_at)
        seen = state.execute("SELECT version FROM seen WHERE id=?", (str(message.pk),)).fetchone()
        if seen and seen[0] == message.updated_at.isoformat():
            continue
        batch.append(message)
        if len(batch) == 20:
            sync_batch(client, state, batch)
            count += len(batch)
            batch = []
    if batch:
        sync_batch(client, state, batch)
        count += len(batch)
    if not count:
        api_post(client, "messages", {"messages": []})
    # Advance only after the whole scan succeeded. A failed row remains retryable.
    state.execute("INSERT OR REPLACE INTO metadata (key, value) VALUES ('cursor', ?)", (newest.isoformat(),))
    state.commit()
    return count


def main():
    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
    logging.getLogger("httpx").setLevel(logging.WARNING)
    base = os.environ.get("SEFAR_APP_URL", "").rstrip("/")
    token = os.environ.get("SEFAR_API_TOKEN", "")
    workspace = os.environ.get("SEFAR_BRIGHTBEAN_WORKSPACE_ID", "")
    if not base.startswith("https://") or not token or not workspace:
        raise SystemExit("Set SEFAR_APP_URL (HTTPS), SEFAR_API_TOKEN and SEFAR_BRIGHTBEAN_WORKSPACE_ID before enabling the connector.")
    workspace = str(uuid.UUID(workspace))
    os.environ.setdefault("DJANGO_SETTINGS_MODULE", "config.settings.production")
    import django
    django.setup()

    folder = Path("/app/media/.sefar-bridge")
    folder.mkdir(mode=0o700, exist_ok=True)
    lock = (folder / "process.lock").open("w")
    fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
    state = sqlite3.connect(folder / f"{workspace}.sqlite3")
    state.execute("CREATE TABLE IF NOT EXISTS seen (id TEXT PRIMARY KEY, version TEXT NOT NULL)")
    state.execute("CREATE TABLE IF NOT EXISTS metadata (key TEXT PRIMARY KEY, value TEXT NOT NULL)")
    state.commit()
    for sig in (signal.SIGINT, signal.SIGTERM):
        signal.signal(sig, lambda *_: STOP.set())

    with httpx.Client(
        base_url=base + "/api/brightbean/",
        headers={"Authorization": f"Bearer {token}", "Accept": "application/json"},
        timeout=httpx.Timeout(120, connect=10), follow_redirects=False,
    ) as client:
        while not STOP.is_set():
            try:
                count = cycle(client, state, workspace)
                if count:
                    LOG.info("Synced %d message updates", count)
                STOP.wait(10)
            except Exception as error:
                # Only class/status. Never interpolate an HTTP exception carrying headers.
                message = str(error) if isinstance(error, AppApiError) else type(error).__name__
                LOG.warning("Sync pending; retrying in 30 seconds (%s)", message)
                STOP.wait(30)


if __name__ == "__main__":
    main()
