<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use RealRashid\SweetAlert\Facades\Alert;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('crud.users.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
         $roles = Role::all();
        /* $permissions = Permission::all(); */
        return view('crud.users.create', compact('roles'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validación
        $request->validate([
            'name' => 'required|max:254',
            'passport' => 'nullable|unique:users,passport',
            'email' => 'required|unique:users,email'
        ]);

        // Creando usuario
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'passport' => $request->passport,
            'password' => bcrypt('sefar2021'),
            'password_md5' => md5('sefar2021'),
            'email_verified_at' => date('Y-m-d H:i:s')
        ]);

        // Asignando roles seleccionados
        $roles = Role::all();
        foreach($roles as $role){
            if($request->input("role" . $role->id)){
                $user->assignRole($role->name);
            }
        }
        
        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha creado el usuario: ' . $request->name);
        
        // Redireccionar a la vista index
        return redirect()->route('crud.users.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(User $user)
    {
        $roles = Role::all();
        return view('crud.users.edit', compact('user', 'roles'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(User $user)
    {
        $roles = Role::all();
        $permissions = Permission::all();
        return view('crud.users.edit', compact('user', 'roles', 'permissions'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        //dd($request->passport);
        // Validación
        if (is_null($request->passport)){
            $request->validate([
                'name' => 'required|max:254',
                'email' => 'email|required|unique:users,email,'.$user->id
            ]);
        }else{
            $request->validate([
                'name' => 'required|max:254',
                'passport' => 'unique:users,passport,'.$user->id,
                'email' => 'email|required|unique:users,email,'.$user->id
            ]);
        }

        // Actualizando usuario
        $user->name = $request->name;
        $user->email = $request->email;
        $user->passport = $request->passport;
        if($request->password){
            $user->password = bcrypt($request->password);
            $user->password_md5 = md5($request->password);
        }
            
        $user->save();

        // Actualizando los roles del usuario
        $roles = Role::all();
        foreach($roles as $role){
            if($request->input("role" . $role->id)){
                $user->assignRole($role->name);
            }else {
                $user->removeRole($role->name);
            }
        }

        // Actualizando los permisos del usuario
        $permissions = Permission::all();
        foreach($permissions as $permission){
            if($request->input("permiso" . $permission->id)){
                $user->givePermissionTo($permission->name);
            }else {
                $user->revokePermissionTo($permission->name);
            }
        }

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha actualizado el usuario: ' . $request->name);
        
        // Redireccionar a la vista index
        return redirect()->route('crud.users.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        $nombre = $user->name;
        
        $user->delete();

        Alert::info('¡Advertencia!', 'Se ha eliminado el usuario: ' . $nombre);

        return redirect()->route('crud.users.index');
    }
}
