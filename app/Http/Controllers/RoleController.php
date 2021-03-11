<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use RealRashid\SweetAlert\Facades\Alert;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('crud.roles.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $permissions = Permission::all();
        return view('crud.roles.create', compact('permissions'));
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
            'name' => 'required|max:254'
        ]);

        // almacenando rol
        $role = Role::create(['name' => $request->name]);
        
        // Asignando permisos seleccionados al rol
        $permissions = Permission::all();
        foreach($permissions as $permission){
            if($request->input("permiso" . $permission->id)){
                $role->givePermissionTo($permission->name);
            }
        }

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha creado el rol: ' . $request->name);
        
        // Redireccionar a la vista index
        return redirect()->route('crud.roles.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Role $role)
    {
        $permissions = permission::all();
        return view('crud.roles.edit', compact('role', 'permissions'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Role $role)
    {
        $permissions = permission::all();
        return view('crud.roles.edit', compact('role', 'permissions'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Role $role)
    {
        // Validación
        $request->validate([
            'name' => 'required|max:254'
        ]);

        // actualizando rol
        $role->name = $request->name;
        $role->save();

        // Actualizando permisos seleccionados al rol
        $permissions = Permission::all();
        foreach($permissions as $permission){
            if($request->input("permiso" . $permission->id)){
                $role->givePermissionTo($permission->name);
                //$roles->find($role->id)->givePermissionTo($permission);
            }else {
                $role->revokePermissionTo($permission->name);
                //$roles->find($role->id)->revokePermissionTo($permission);
            }
        }

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha actualizado el rol a : ' . $request->name);
        
        // Redireccionar a la vista index
        return redirect()->route('crud.roles.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Role $role)
    {
        $nombre = $role->name;
        
        $role->delete();

        Alert::info('¡Advertencia!', 'Se ha eliminado el rol: ' . $nombre);

        return redirect()->route('crud.roles.index');
    }
}
