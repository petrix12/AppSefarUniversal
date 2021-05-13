<?php
// Mostrar documentos
function mostrarDocumentos($agclientes, $IDPersona, $tipo = 'tree'){
    try{
        $pasaporte = $agclientes->where('IDPersona',$IDPersona)->first()->IDCliente;
    }catch(Exception $e){
        return null;
    }
    $persona = GetPersona($IDPersona);
    $carpeta = 'doc/P'.$pasaporte.'/'.$persona.'/';
    if ( ! file_exists($carpeta)){ 
        $carpeta = 'doc/P0'.$pasaporte.'/Cliente/';
    }
    if ( ! file_exists($carpeta)){ 
        $carpeta = null;
    }
    if($carpeta){
        switch ($tipo) {
            case 'tree':
                ?>
                <button onclick="document.getElementById('verDocumentos<?php echo $IDPersona; ?>').showModal()"><span class="folder ctrSefar" title="Ver documentos"><i class="far fa-folder-open"></i></span></button>
                <?php
                break;
            case 'albero':
                ?>
                <button onclick="document.getElementById('verDocumentos<?php echo $IDPersona; ?>').showModal()"><span class="documentos ctrSefar" title="Ver documentos"><i class="far fa-folder-open"></i></span></button>
                <?php
                break;
            default:
                return null;
        }
        ?>
        <dialog id="verDocumentos<?php echo $IDPersona; ?>" class="container h-auto w-11/12 mt-3 md:w-1/2 p-5 bg-white rounded-md">    
            <div class="flex flex-col w-full h-auto ">
                <!-- Título -->
                <div class="flex w-full h-auto justify-center items-center">
                    <div class="flex w-10/12 h-auto py-3 justify-center items-center text-lg font-bold">
                        <?php echo 'Documentos ' . GetNombres($agclientes,$IDPersona) . ' ' . GetApellidos($agclientes,$IDPersona); ?>
                    </div>
                    <div onclick="document.getElementById('verDocumentos<?php echo $IDPersona; ?>').close();" class="flex w-1/12 h-auto justify-center cursor-pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </div>
                </div>
                <!-- Contenido-->
                <div class="flex w-full h-auto py-4 px-2 justify-center items-center bg-gray-200 rounded text-center text-gray-500">
                    <?php
                        if (file_exists($carpeta)){
                            echo '<ul>';
                            if ($handler = opendir($carpeta)) {
                                while (false !== ($file = readdir($handler))) {
                                    if($file == '.' or $file == '..'){
                                        continue;
                                    }
                                    echo '<li><a href="'.asset($carpeta.$file).'" target="_blank">'.$file.'</a></li>';
                                }
                                closedir($handler);
                            }
                            echo '</ul>';
                        }
                    ?>
                </div>
                <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                    <button onclick="document.getElementById('verDocumentos<?php echo $IDPersona; ?>').close();" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Listo
                    </button>
                </div>
            </div>
        </dialog>
        <?php
    }
}
?>