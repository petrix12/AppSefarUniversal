"""Run in the BrightBean image; mocks prevent database writes and channel sends."""

import datetime as dt
import importlib.util
from pathlib import Path
import sqlite3
import sys
from types import SimpleNamespace as Obj
import unittest
from unittest.mock import patch

import django
import httpx
sys.path.insert(0, '/app')
django.setup()

spec = importlib.util.spec_from_file_location("sefar_bridge", Path(__file__).with_name("sefar_bridge.py"))
bridge = importlib.util.module_from_spec(spec)
spec.loader.exec_module(bridge)


def message(**changes):
    now = dt.datetime.now(dt.timezone.utc)
    contact = Obj(pk="contact", first_name="Test", last_name="", email="", phone="+580000")
    conversation = Obj(pk="thread", contact=contact, workspace=Obj(pk="workspace"), state="open", automation_paused_until=None)
    return Obj(**{
        "pk": "message", "workspace_id": "00000000-0000-4000-8000-000000000001", "conversation_id": "thread",
        "channel_connection_id": "channel", "channel_connection": Obj(platform="whatsapp"),
        "conversation": conversation, "direction": "in", "source": "", "status": "sent",
        "internal": False, "body": {"blocks": [{"type": "text", "text": "Hola 👋"}]},
        "created_at": now, "updated_at": now, **changes,
        "refresh_from_db": lambda **kwargs: None,
    })


class BridgeTest(unittest.TestCase):
    def state(self):
        state = sqlite3.connect(":memory:")
        state.execute("CREATE TABLE seen (id TEXT PRIMARY KEY, version TEXT NOT NULL)")
        return state

    def test_real_brightbean_message_shape_is_understood(self):
        from apps.channels.events import OutboundMessage, TextBlock
        body = OutboundMessage(blocks=(TextBlock(text="Hola 👋"),)).to_body()
        self.assertEqual("Hola 👋", bridge.message_payload(message(body=body))["text"])

    def test_deleted_content_is_redacted(self):
        result = bridge.message_payload(message(status="deleted"))
        self.assertIsNone(result["text"])
        self.assertEqual({}, result["body"])

    def test_api_errors_exclude_the_response_body_and_credentials(self):
        transport = httpx.MockTransport(lambda request: httpx.Response(403, text='private-token-and-message'))
        with httpx.Client(transport=transport, base_url='https://app.example/api/') as client:
            with self.assertRaises(bridge.AppApiError) as failure:
                bridge.api_post(client, 'messages', {'messages': []})
        self.assertIn('403', str(failure.exception))
        self.assertNotIn('private-token-and-message', str(failure.exception))

    def test_outgoing_internal_and_old_messages_are_not_answered(self):
        for changes in [{"direction": "out"}, {"internal": True}, {"status": "deleted"},
                        {"created_at": dt.datetime.now(dt.timezone.utc) - dt.timedelta(hours=1)}]:
            self.assertFalse(bridge.may_reply(message(**changes)))

    def test_agent_takeover_prevents_a_reply(self):
        from apps.messaging.models import Conversation, Message
        with patch.object(Conversation.objects, "for_workspace") as conversations, \
             patch.object(Message.objects, "for_workspace") as messages:
            conversations.return_value.get.return_value = message().conversation
            messages.return_value.filter.return_value.exists.return_value = True
            self.assertFalse(bridge.may_reply(message()))
            messages.return_value.filter.return_value.exists.return_value = False
            self.assertTrue(bridge.may_reply(message()))
            conversations.return_value.get.return_value.automation_paused_until = dt.datetime.now(dt.timezone.utc) + dt.timedelta(minutes=30)
            self.assertFalse(bridge.may_reply(message()))

    def test_agent_takeover_during_model_call_prevents_the_send(self):
        with patch.object(bridge, "may_reply", side_effect=[True, False]), \
             patch.object(bridge, "api_post", side_effect=[{"data": [{"external_id": "message", "id": 1}]},
                                                         {"data": {"reply": "Answer", "idempotency_key": "stable-key"}}]), \
             patch("apps.messaging.services.send_via_api") as send:
            bridge.sync_batch(None, self.state(), [message()])
            send.assert_not_called()

    def test_retries_reuse_the_same_native_idempotency_key(self):
        with patch.object(bridge, "may_reply", return_value=True), \
             patch.object(bridge, "api_post", side_effect=[
                 {"data": [{"external_id": "message", "id": 1}]},
                 {"data": {"reply": "Answer", "idempotency_key": "stable-key"}},
                 {"data": [{"external_id": "message", "id": 1}]},
                 {"data": {"reply": "Answer", "idempotency_key": "stable-key"}},
             ]), patch("apps.messaging.services.send_via_api") as send:
            state = self.state()
            bridge.sync_batch(None, state, [message()])
            bridge.sync_batch(None, state, [message()])
            self.assertEqual(2, send.call_count)
            self.assertEqual(["stable-key", "stable-key"], [call.kwargs["idempotency_key"] for call in send.call_args_list])
            self.assertEqual(1, state.execute("SELECT COUNT(*) FROM seen").fetchone()[0])


if __name__ == "__main__":
    unittest.main()
