<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Compras;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;

    public const CLIENT_BLOCK_REASONS = [
        'retired' => 'Registro retirado. Contacta con la empresa para revisar y resolver tu situación.',
        'test_data' => 'Registro identificado como información de prueba. Contacta con la empresa si necesitas una revisión.',
        'payment_default' => 'Pagos pendientes o incumplidos',
        'documentation_issues' => 'Documentación pendiente o inconsistente',
        'terms_violation' => 'Incumplimiento de términos y condiciones',
        'inappropriate_behavior' => 'Conducta inadecuada o uso indebido de la plataforma',
        'security_review' => 'Revisión de seguridad de la cuenta',
        'other' => 'Otro',
    ];

    protected $guarded = ['is_superadmin', 'google_id'];

    protected $hidden = [
        'password',
        'password_md5',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
        'google_id',
    ];

    protected $casts = [
        'is_superadmin' => 'boolean',
        'email_verified_at' => 'datetime',
        'arraycos' => 'array',
        'arraycos_expire' => 'datetime',
        'contrato' => 'boolean',
        'exclude_from_task_assignment' => 'boolean',
        'task_assignment_daily_limit' => 'integer',
        'last_task_reassigned_at' => 'datetime',
        'task_reassignment_locked_at' => 'datetime',
        'google_review_completed_at' => 'datetime',
    ];

    public function accessBlock()
    {
        return $this->hasOne(UserAccessBlock::class, 'user_id');
    }

    public function isSuperadmin(): bool
    {
        return (bool) $this->getAttribute('is_superadmin') && ! $this->hasRole('Cliente');
    }

    public function hasIndividualClientBlock(): bool
    {
        if (! Schema::hasTable('user_access_blocks')) {
            return false;
        }

        return $this->relationLoaded('accessBlock')
            ? $this->getRelation('accessBlock') !== null
            : $this->accessBlock()->exists();
    }

    public function clientBlockReasonDescription(): ?string
    {
        if (! Schema::hasTable('user_access_blocks')) {
            return null;
        }

        $block = $this->relationLoaded('accessBlock')
            ? $this->getRelation('accessBlock')
            : $this->accessBlock()->first();

        if (! $block) {
            return null;
        }

        if ($block->reason === 'other') {
            return filled($block->reason_other)
                ? 'Otro: ' . $block->reason_other
                : self::CLIENT_BLOCK_REASONS['other'];
        }

        return self::CLIENT_BLOCK_REASONS[$block->reason] ?? $block->reason;
    }

    public function isClientAppAccessBlocked(): bool
    {
        return $this->isCliente() && $this->hasIndividualClientBlock();
    }

    protected $appends = [
        'profile_photo_url',
    ];

    private ?array $pendingChangeAudit = null;

    private const AUDIT_REDACTED_FIELDS = [
        'password',
        'password_md5',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    private static function registerChangeAuditObservers(): void
    {
        static::updating(function (self $user): void {
            $changes = collect($user->getDirty())
                ->except(['updated_at'])
                ->all();

            if ($changes === []) {
                return;
            }

            $oldValues = [];
            $newValues = [];

            foreach ($changes as $field => $value) {
                $oldValues[$field] = self::auditValue($field, $user->getRawOriginal($field));
                $newValues[$field] = self::auditValue($field, $value);
            }

            $user->pendingChangeAudit = compact('oldValues', 'newValues');
        });

        static::updated(function (self $user): void {
            if ($user->pendingChangeAudit === null) {
                return;
            }

            self::recordChangeAudit(
                $user,
                $user->pendingChangeAudit['oldValues'],
                $user->pendingChangeAudit['newValues'],
            );
            $user->pendingChangeAudit = null;
        });
    }

    protected function performUpdate(Builder $query)
    {
        if (! $this->isDirty('passport') || ! $this->getConnection()->getSchemaBuilder()->hasTable('user_passport_histories')) {
            return parent::performUpdate($query);
        }

        // Read the stored value under a lock so even a stale model preserves
        // the passport it actually replaces. Save both records atomically.
        return $this->getConnection()->transaction(function () use ($query) {
            $passport = trim((string) $this->newModelQuery()->whereKey($this->getKey())->lockForUpdate()->value('passport'));
            $saved = parent::performUpdate($query);
            $replacement = trim((string) $this->passport);

            if ($saved && $this->wasChanged('passport') && $passport !== '' && strcasecmp($passport, $replacement) !== 0) {
                $request = app()->bound('request') ? app('request') : null;
                $this->passportHistories()->create([
                    'passport' => $passport,
                    'replacement_passport' => $replacement !== '' ? $replacement : null,
                    'changed_by_user_id' => Auth::id(),
                    'source' => $request?->route()?->getName() ?? (app()->runningInConsole() ? 'console' : 'system'),
                ]);
                $this->unsetRelation('passportHistories');
            }

            return $saved;
        });
    }

    public const SALES_PROFILE_ROLES = [
        'Coord. de Nacionalidad y Genealogía',
        'Ventas',
        'Analista',
        'ATC',
    ];

    /**
     * Usar S3 como disco para fotos de perfil
     */
    public function profilePhotoDisk(): string
    {
        return 's3';
    }

    /**
     * Subir foto de perfil a S3 (pública) y guardar solo el path
     */
    public function updateProfilePhoto(UploadedFile $photo, $storagePath = 'profile-photos')
    {
        tap($this->profile_photo_path, function ($previous) use ($photo, $storagePath) {

            $path = $photo->storePublicly(
                $storagePath,
                ['disk' => $this->profilePhotoDisk()]
            );

            $this->forceFill([
                'profile_photo_path' => $path,
            ])->save();

            if ($previous) {
                Storage::disk($this->profilePhotoDisk())->delete($previous);
            }
        });
    }

    /**
     * Imagen para AdminLTE
     */
    public function adminlte_image()
    {
        return $this->profile_photo_url;
    }

    /**
     * Descripción para AdminLTE
     */
    public function adminlte_desc()
    {
        $role = $this->getRoleNames();

        return $role->first() ?? "Sin rol asignado";
    }

    /**
     * URL perfil AdminLTE
     */
    public function adminlte_profile_url()
    {
        return 'user/profile';
    }

    /**
     * Relaciones
     */
    public function compras()
    {
        return $this->hasMany(Compras::class, 'id_user', 'id');
    }

    public function treePeople()
    {
        return $this->hasMany(Agcliente::class, 'IDCliente', 'passport');
    }

    public function genealogyTreeLink()
    {
        return $this->hasOne(UserGenealogyTreeLink::class);
    }

    public function changeAudits()
    {
        return $this->hasMany(UserChangeAudit::class);
    }

    public function passportHistories()
    {
        return $this->hasMany(UserPassportHistory::class)->latest('created_at')->latest('id');
    }

    public static function recordChangeAudit(self $user, array $oldValues, array $newValues): void
    {
        if (! Schema::hasTable('user_change_audits')) {
            return;
        }

        $request = app()->bound('request') ? app('request') : null;

        UserChangeAudit::create([
            'user_id' => $user->id,
            'changed_by_user_id' => Auth::id(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'source' => $request?->route()?->getName() ?? (app()->runningInConsole() ? 'console' : 'system'),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    private static function auditValue(string $field, mixed $value): mixed
    {
        if (in_array($field, self::AUDIT_REDACTED_FIELDS, true)) {
            return '[oculto]';
        }

        if (is_string($value) && mb_strlen($value) > 4000) {
            return mb_substr($value, 0, 4000) . '… [truncado]';
        }

        return $value;
    }

    public function getGenealogyTreeIdAttribute(): ?string
    {
        $link = $this->relationLoaded('genealogyTreeLink')
            ? $this->getRelation('genealogyTreeLink')
            : $this->genealogyTreeLink()->first();

        return $link?->tree_id;
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function customers()
    {
        return $this->hasMany(User::class, 'owner_id');
    }

    public function notes()
    {
        return $this->hasMany(ClientNote::class, 'client_id');
    }

    public function assignedLeads()
    {
        return $this->hasMany(Lead::class, 'user_id');
    }

    public function convertedLeads()
    {
        return $this->hasMany(Lead::class, 'converted_user_id');
    }

    public function crmActivities()
    {
        return $this->hasMany(CrmActivity::class, 'client_id');
    }

    public function crmQuotes()
    {
        return $this->hasMany(CrmQuote::class, 'client_id');
    }

    public function listas()
    {
        return $this->belongsToMany(\App\Models\Lista::class, 'list_user', 'user_id', 'list_id')
            ->withPivot(['id', 'contacted', 'contacted_at', 'contact_note'])
            ->withTimestamps();
    }

    /**
     * Extensible client fields. New HubSpot, Monday and Teamleader properties
     * must be stored here instead of adding columns to users.
     */
    public function customFieldValues()
    {
        return $this->hasMany(\App\Models\CustomFieldValue::class, 'entity_id')
            ->where('entity_type', \App\Models\CustomFieldDefinition::ENTITY_CLIENT);
    }

    public function externalEntityLinks()
    {
        return $this->hasMany(\App\Models\ExternalEntityLink::class, 'entity_id')
            ->where('entity_type', \App\Models\CustomFieldDefinition::ENTITY_CLIENT);
    }

    public function workflowMemberships()
    {
        return $this->hasMany(\App\Models\WorkflowMembership::class, 'entity_id')
            ->where('entity_type', \App\Models\CustomFieldDefinition::ENTITY_CLIENT);
    }

    public function hubspotOwnerLink()
    {
        return $this->hasOne(\App\Models\HubspotOwnerUser::class);
    }

    public function hubspotUserProvisioning()
    {
        return $this->hasOne(\App\Models\HubspotUserProvisioning::class);
    }

    public function strategicSuggestions()
    {
        return $this->hasMany(\App\Models\StrategicSuggestion::class, 'user_id');
    }

    public function strategicSuggestionReplies()
    {
        return $this->hasMany(\App\Models\StrategicSuggestionReply::class, 'user_id');
    }

    public function isCliente(): bool
    {
        return $this->hasRole('Cliente');
    }

    /**
     * Contactos/clientes del CRM. Se mantiene como scope de transición para
     * que las pantallas de clientes no mezclen cuentas internas.
     */
    public function scopeClients(Builder $query): Builder
    {
        return $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', 'Cliente'));
    }

    public function scopeWithCosStatus(Builder $query, string $status): Builder
    {
        if ($status === '__without_status') {
            return $query->where(fn (Builder $users) => $users->whereNull('users.arraycos')
                ->orWhereJsonLength('users.arraycos', 0));
        }

        if ($query->getConnection()->getDriverName() === 'sqlite') {
            return $query->whereRaw("exists (select 1 from json_each(users.arraycos) as process where json_extract(process.value, '$.currentStepName') = ?)", [$status]);
        }

        return $query->whereJsonContains('users.arraycos', [['currentStepName' => $status]]);
    }

    /**
     * Cuentas internas autenticables que no representan un contacto cliente.
     */
    public function scopeInternalUsers(Builder $query): Builder
    {
        return $query->whereDoesntHave('roles', fn (Builder $roles) => $roles->where('name', 'Cliente'));
    }

    public function isInternalUser(): bool
    {
        return ! $this->isCliente();
    }

    public function canViewSalesProfile(): bool
    {
        return $this->hasAnyRole(self::SALES_PROFILE_ROLES);
    }

    protected static function booted(): void
    {
        self::registerChangeAuditObservers();

        static::created(function ($user) {
            if (!$user->roles()->exists()) {
                $user->assignRole('Cliente');
            }
        });
    }
}
