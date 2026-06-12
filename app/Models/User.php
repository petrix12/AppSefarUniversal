<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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

    protected $guarded = [];

    protected $hidden = [
        'password',
        'password_md5',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'arraycos' => 'array',
        'arraycos_expire' => 'datetime',
        'exclude_from_task_assignment' => 'boolean',
        'task_assignment_daily_limit' => 'integer',
        'last_task_reassigned_at' => 'datetime',
        'task_reassignment_locked_at' => 'datetime',
    ];

    protected $appends = [
        'profile_photo_url',
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

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function customers()
    {
        return $this->hasMany(User::class, 'owner_id');
    }

    public function listas()
    {
        return $this->belongsToMany(\App\Models\Lista::class, 'list_user', 'user_id', 'list_id')
            ->withPivot(['id', 'contacted', 'contacted_at', 'contact_note'])
            ->withTimestamps();
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

    protected static function booted()
    {
        static::created(function ($user) {
            if (!$user->roles()->exists()) {
                $user->assignRole('Cliente');
            }
        });
    }
}
