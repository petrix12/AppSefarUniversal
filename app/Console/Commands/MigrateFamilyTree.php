<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Agcliente; // Asegúrate de ajustar el namespace
use Illuminate\Support\Facades\DB;

class MigrateFamilyTree extends Command
{
    protected $signature = 'migrate:familytree';
    protected $description = 'Migrates family tree data for all clients';

    public function handle()
    {
        $clientes = Agcliente::select('IDCliente', DB::raw('COUNT(*) AS Cantidad'), 'created_at')
                    ->groupBy('IDCliente')
                    ->havingRaw('COUNT(*) > 3')
                    ->orderBy('id', 'desc')
                    ->get();

        foreach ($clientes as $cliente) {
            $IDCliente = $cliente->IDCliente;

            //revisar padres

            $searchCliente = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',1)->first();
            $existe = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',1)->get();

            $padreQuery = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',2)->first();

            $madreQuery = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',3)->first();

            if ($padreQuery || $madreQuery) {
                $data = [
                    'migradoNuevoID' => 1,
                ];

                // Si existe el padre, añade 'idPadreNew' y 'IDPadre' al array de actualización
                if ($padreQuery) {
                    $data['idPadreNew'] = $padreQuery->id;
                    $data['IDPadre'] = 2;
                }

                // Si existe la madre, añade 'idMadreNew' y 'IDMadre' al array de actualización
                if ($madreQuery) {
                    $data['idMadreNew'] = $madreQuery->id;
                    $data['IDMadre'] = 3;
                }

                // Ejecuta la actualización
                DB::table('agclientes')
                    ->where('id', $searchCliente->id)
                    ->update($data);
            }

            $people = json_decode(json_encode(Agcliente::where("IDCliente",$IDCliente)->get()),true);

            //Asignar ids de padres al nodo 0 (en caso de no tenerlo)
            if (count($people)>2){
                if(!isset($people[0]['IDMadre'])){
                    if($people[1]["Sexo"]=="F"){
                        $people[0]['IDMadre']=$people[1]['IDPersona'];
                        $people[0]['IDPadre']=$people[2]['IDPersona'];
                    } else {
                        $people[0]['IDMadre']=$people[2]['IDPersona'];
                        $people[0]['IDPadre']=$people[1]['IDPersona'];
                    }
                }
            }

            //Eliminar basura de los ids de los padres        
            foreach ($people as $key => $person) {
                if ($person['IDMadre']<1){
                    $people[$key]['IDMadre']=null;
                }
                if ($person['IDPadre']<1){
                    $people[$key]['IDPadre']=null;
                }
            }

            $idPersonaToIdMap = [];
            foreach ($people as $item) {
                $idPersonaToIdMap[$item['IDPersona']] = $item['id'];
            }

            foreach ($people as &$item) {
                if ($person["migradoNuevoID"]==0){
                    if (isset($item['IDPadre']) && isset($idPersonaToIdMap[$item['IDPadre']])) {
                        $item['idPadreNew'] = $idPersonaToIdMap[$item['IDPadre']];
                    } else {
                        $item['idPadreNew'] = null;
                    }
                    if (isset($item['IDMadre']) && isset($idPersonaToIdMap[$item['IDMadre']])) {
                        $item['idMadreNew'] = $idPersonaToIdMap[$item['IDMadre']];
                    } else {
                        $item['idMadreNew'] = null;
                    }
                }
            }

            foreach ($people as $person) {
                if($person["migradoNuevoID"]==0){
                    DB::table('agclientes')
                    ->where('id', $person['id'])
                    ->update([
                        'idPadreNew' => $person['idPadreNew'],
                        'idMadreNew' => $person['idMadreNew'],
                        'migradoNuevoID' => 1
                    ]);
                }
            }

            $arreglo = $people;
            $generaciones = array();

            foreach ($arreglo as $id => $persona) {
                if ($persona['idPadreNew'] === null && $persona['idMadreNew'] === null) {
                    $generaciones[$persona["id"]] = 1;
                }
            }
            
            $cambio = true;
            while ($cambio) {
                $cambio = false;
                foreach ($arreglo as $id => $persona) {
                    $generacionPadre = isset($generaciones[$persona['idPadreNew']]) ? $generaciones[$persona['idPadreNew']] : 0;
                    $generacionMadre = isset($generaciones[$persona['idMadreNew']]) ? $generaciones[$persona['idMadreNew']] : 0;
                    $generacionActual = max($generacionPadre, $generacionMadre) + 1;
                    
                    if (!isset($generaciones[$persona["id"]]) || $generaciones[$persona["id"]] != $generacionActual) {
                        $generaciones[$persona["id"]] = $generacionActual;
                        $cambio = true;
                    }
                }
            }

            $this->info($IDCliente.' migrated.');
        }

        $this->info('Family tree migration completed.');
    }
}