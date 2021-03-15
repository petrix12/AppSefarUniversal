<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Auth;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'passport',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'profile_photo_url',
    ];

    // Permite incorporar una imagen de usuario
    // Se debe configurar en config\adminlte.php: 'usermenu_image' => true,
    public function adminlte_image(){
        //return 'https://picsum.photos/300/300'; /* Retorna una imagen aleatoria*/
        
        return Auth::user()->profile_photo_url;
    }

    // Permite incorporar alguna descripción del usuario
    // Se debe configurar en config\adminlte.php: 'usermenu_desc' => ' => true,
    public function adminlte_desc(){
        $role = Auth::user()->getRoleNames();
        if(count($role)>=1){
            $nombre_rol = $role[0];
        }else{
            $nombre_rol = "Sin rol asignado";
        }
        return $nombre_rol;
    }

    // Permite incorporar el perfil
    // Se debe configurar en config\adminlte.php: 'usermenu_profile_url' => true,
    public function adminlte_profile_url(){
        return 'user/profile';
    }
}
