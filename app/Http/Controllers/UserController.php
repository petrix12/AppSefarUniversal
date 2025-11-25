<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Servicio;
use App\Models\HsReferido;
use App\Models\Compras;
use App\Models\Factura;
use App\Models\CosVisit;
use App\Models\Negocio;
use Illuminate\Support\Facades\Http;
use App\Models\File;
use App\Models\AssocTlHs;
use Monday;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Agcliente;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use RealRashid\SweetAlert\Facades\Alert;
use HubSpot;
use HubSpot\Client\Crm\Deals\Model\AssociationSpec;
use HubSpot\Client\Crm\Associations\ApiException;
use HubSpot\Client\Crm\Associations\Model\BatchInputPublicObjectId;
use HubSpot\Client\Crm\Associations\Model\PublicObjectId;
use Illuminate\Support\Facades\Input;
use App\Services\TeamleaderService;
use App\Services\HubspotService;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Storage;
use App\Models\MondayData;
use App\Models\MondayFormBuilder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Carbon\Carbon;
use App\Models\DocumentRequest;

class UserController extends Controller
{
    protected $teamleaderService;
    protected $hubspotService;

    public function __construct(TeamleaderService $teamleaderService, HubspotService $hubspotService)
    {
        $this->teamleaderService = $teamleaderService;
        $this->hubspotService = $hubspotService;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('crud.users.index');
    }

    public function checkEmail(Request $request)
    {
        // Validar que el email venga en la petición
        $request->validate([
            'email' => 'required|email'
        ]);

        // Verificar si el email existe
        $exists = User::where('email', $request->email)->exists();

        return response()->json([
            'success' => true,
            'exists' => $exists,
            'email' => $request->email
        ]);
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

    public function mypassword(Request $request)
    {
        // Validación de datos
        $validatedData = $request->validate([
            'password' => 'required|min:8', // Laravel busca automáticamente un campo `password_confirmation`
        ]);

        // Actualiza la contraseña del usuario
        $user = Auth::user(); // Obtiene al usuario autenticado
        $user->password = bcrypt($request->password);
        $user->password_md5 = md5($request->password);
        $user->save();

        // Retorna una respuesta JSON
        return response()->json(['success' => true, 'message' => 'Contraseña actualizada correctamente']);
    }

    public function adminchangepassword(Request $request)
    {
        // Valida los datos del formulario
        $validatedData = $request->validate([
            'id' => 'required|exists:users,id',
            'password' => 'required|min:8',
        ], [
            'id.required' => 'El ID del usuario es obligatorio.',
            'id.exists' => 'El usuario especificado no existe.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
        ]);

        // Encuentra al usuario
        $user = User::findOrFail($request->id);

        // Actualiza la contraseña
        $user->password = bcrypt($request->password);
        $user->password_md5 = md5($request->password);
        $user->save();

        // Retorna una respuesta JSON
        return response()->json(['success' => true, 'message' => 'Contraseña actualizada correctamente']);
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(User $user)
    {
        // Teamleader
        if (is_null($user->tl_id)) {
            $TLcontactByEmail = $this->teamleaderService->searchContactByEmail($user->email);

            if (is_null($TLcontactByEmail)) {
                $newContact = $this->teamleaderService->createContact($user);
                $user->tl_id = $newContact['id'];
            } else {
                $user->tl_id = $TLcontactByEmail['id'];
            }

            $user->save();
        }

        $TLcontact = $this->teamleaderService->getContactById($user->tl_id);
        $TLdeals = $this->teamleaderService->getDealsByContactId($user->tl_id);

        // HubSpot
        if (is_null($user->hs_id)) {
            $HScontactByEmail = $this->hubspotService->searchContactByEmail($user->email);
            $user->hs_id = $HScontactByEmail['id'];
            $user->save();
        }

        $HScontact = $this->hubspotService->getContactById($user->hs_id);
        $HSdeals = $this->hubspotService->getDealsByContactId($user->hs_id);

        // Monday
        if ($user->monday_id) {
            $query = "
                items(ids: [{$user->monday_id}]) {
                    id
                    name
                    column_values {
                        id
                        text
                    }
                }
            ";

            $result = json_decode(json_encode(Monday::customQuery($query)), true);
            $mondayUserDetails = $result['items'][0] ?? null;
        } else {
            $mondayUserDetails = $this->searchUserInMonday($user->passport, $user);
        }

        return view('crud.users.status', compact('TLcontact', 'TLdeals', 'HScontact', 'HSdeals', 'mondayUserDetails'));
    }

    public function getuserstatus_ventas(Agcliente $agcliente)
    {
        $userpre = User::where('passport', '=', $agcliente->IDCliente)->get();

        $user = $userpre[0];
    }

    public function my_status()
    {
        $user = Auth::user();

        /*INFO DE MONDAY*/


        //Tablas que NO voy a llamar
        $preventmondayids = [
            "3016568563",
            "3016439235",
            "3016427689",
            "3016425138",
            "2922023945",
            "2921955649",
            "2840594467",
            "2369283634",
            "2267403210",
            "2178303858",
            "2135021222",
            "1721146413",
            "1708668268",
            "1708668252",
            "1531350971",
            "1078272587",
            "1078272574",
            "1078272554",
            "1029708419",
            "867510225",
        ];

        //me traigo todas las tablas de monday
        $mondayboards_temp = json_decode(json_encode(Monday::customQuery("boards (limit: 500) { id name }")),true);

        $mondayboards = $mondayboards_temp["boards"];

        $usuario_mdy = [];

        $vartest = 0;

        //proceso la informacion

        foreach ($mondayboards as $key => $value) {
            if (!str_contains($value["name"], 'Subelementos de ')) {
                if( !in_array($value["id"], $preventmondayids) ){

                    //me traigo toda la informacion de una tabla en especifico

                    $mondayboards_temp = json_decode(json_encode(Monday::customQuery("boards (ids: " . $value["id"] . ")  { id name items { name column_values { title text id } } }")),true);

                    //proceso la informacion

                    foreach ($mondayboards_temp["boards"][0]["items"] as $key => $item) {
                        foreach ($item["column_values"] as $keycv => $cv) {
                            if ($cv["id"]=="enlace"){
                                if ($cv["text"]="https://app.sefaruniversal.com/tree/". $user->passport){
                                    $vartest = 1;
                                    $usuario_mdy = $item;
                                    $usuario_mdy["tabla_nombre"] = $mondayboards_temp["boards"][0]["name"];
                                }
                                break;
                            }
                        }
                        if($vartest==1){
                            break;
                        }
                    };
                }

            }

            if($vartest==1){
                break;
            }

        }

        /*

                Traigo la información necesaria desde la base de datos

        */
        $servicioHS = Servicio::all();
        $referidosHS = HsReferido::all();
        $familiaresR = Agcliente::where('IDCliente', '=', $user->passport)->get();

        // Llamo a la Api Key de Hubspot
        $hubspot = HubSpot\Factory::createWithAccessToken(env('HUBSPOT_KEY'));

        /*

            Clientes

        */

        //Llamo a la libreria de Clientes de Hubspot y establezco los parametros que voy a usar para la busqueda. En este caso, solo basta el EMAIL del cliente

        $filter = new \HubSpot\Client\Crm\Contacts\Model\Filter();
        $filter
            ->setOperator('EQ')
            ->setPropertyName('email')
            ->setValue($user->email);

        $filterGroup = new \HubSpot\Client\Crm\Contacts\Model\FilterGroup();
        $filterGroup->setFilters([$filter]);

        $searchRequest = new \HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest();
        $searchRequest->setFilterGroups([$filterGroup]);

        //Llamo a todas las propiedades de Hubspot (Si el dia de mañana hay que añadir algo nuevo, la ventaja que tenemos es que ya me estoy trayendo todos los elementos del arreglo)

        $searchRequest->setProperties([
            "aacs_madre",
            "aacs_padre",
            "acta_de_divorcio",
            "activo",
            "aircall_last_call_at",
            "anos_en_residencia_actual",
            "antecedentes_penales_apostillada",
            "apellido_de_casada",
            "apellido_jotform",
            "apellidos___nombre",
            "apellidos_miembro_de_familia_1",
            "apellidos_miembro_de_familia_2",
            "apellidos_miembro_de_familia_3",
            "apellidos_miembro_de_familia_4",
            "apellidos_miembro_de_familia_5",
            "aplicar_cupon",
            "apto_otros_procesos",
            "arbol_genealogico",
            "atribucion_a_marketing",
            "autorizacion_memoramdum_administrativo",
            "autorizacion_para_certificado_cil",
            "autorizacion_para_subsanacion",
            "autorizacion_recurso_de_alzada",
            "bic___swift",
            "calle",
            "captador",
            "carta_motivada",
            "carta_motivada__pendiente_por_revision_",
            "carta_motivada__sin_corregir_",
            "caso_especial",
            "categoria_segun_la_edad",
            "ccse",
            "ccse_archivado_espana",
            "ccse_resultado",
            "certificado_cil",
            "certificado_de_estudio_simple",
            "cif",
            "ciudad_de_nacimiento",
            "ciudad_destino",
            "ciudad_origen",
            "cliente",
            "clientes_formalizados_notificados",
            "clientes_formalizados_sin_notificacion",
            "cm___quemado",
            "cnat___declaracion_sust__de_docs_",
            "codigo_consulta_ministerio",
            "codigo_de_referencia",
            "company_size",
            "constancia_de_residencia",
            "conyuge_interesado_en_proceso",
            "coordinador",
            "coordinador__historico_",
            "cualificacion_profesional",
            "cuantos_hijos_tiene_",
            "cuenta_con_un_patrocinador_que_lo_espere_en_estados_unidos_",
            "cuestionario_recurso_de_alzada",
            "cumbre_5_septiembre",
            "cumpleanos",
            "cupon",
            "date_of_birth",
            "days_to_close",
            "declaracion_cil",
            "declaracion_de_emolumentos",
            "declaracion_jurada_de_residencia",
            "degree",
            "dele",
            "departamento_sefar_universal",
            "detalle_de_la_solicitud",
            "df_active_subscriptions",
            "df_all_payments",
            "df_billing_contact",
            "df_billing_profile",
            "df_canceled_subscriptions",
            "df_discount",
            "df_last_paid_amount",
            "df_last_payment_result_text",
            "df_last_product_purchased",
            "df_next_payment_date",
            "df_outstanding_amount",
            "df_payment_status",
            "df_reference_number",
            "df_remaining_balance",
            "df_stripe_account_id",
            "df_stripe_customer_id",
            "df_tax_amount",
            "df_total_payments_2020",
            "df_total_payments_2021",
            "df_transaction_date",
            "direccion_en_el_pais_de_origen",
            "direccion_jotform",
            "doc__entregados_a_rafaela_almeida",
            "documentos",
            "edad_ninos",
            "edo_civil",
            "el_solicitante_es_el_responsable_del_pago_",
            "email_2",
            "email_alternativo",
            "email_del_responsable_de_pago",
            "enlace_evento",
            "envento_zoom___ultimo",
            "estado_de_datos_y_documentos_de_los_antepasados",
            "etapa_de_ciclo_de_vida_sefar",
            "etiquetas",
            "evento_horarios",
            "evento_imagen__enlace_completo_",
            "fase_actual",
            "fcje",
            "fcje_familiar",
            "fcje_madre",
            "fcje_padre",
            "fecha",
            "fecha_caducidad_pasaporte",
            "fecha_de_caducidad_del_pasaporte",
            "fecha_de_confirmacion_del_cliente_como_recibido__juramentacion_",
            "fecha_de_creacion_teamleader",
            "fecha_de_firma",
            "fecha_de_notificacion_al_cliente_por_email__juramentacion_",
            "fecha_de_retorno_mg_tours",
            "fecha_de_salida_mg_tours",
            "fecha_formalizacion__sistemas___no_tocar_",
            "fecha_nac",
            "field_of_study",
            "first_conversion_date",
            "first_conversion_event_name",
            "first_deal_created_date",
            "flexibilidad_mg_tours",
            "fuente_sefar",
            "gender",
            "genero",
            "gestoria_sefar__documentos_",
            "graduation_date",
            "grupo_familiar",
            "hora_evento__espana_",
            "horario_de_llamada",
            "horario_de_preferencia_para_contactarlo",
            "hs_additional_emails",
            "hs_all_assigned_business_unit_ids",
            "hs_all_contact_vids",
            "hs_analytics_first_touch_converting_campaign",
            "hs_analytics_last_touch_converting_campaign",
            "hs_avatar_filemanager_key",
            "hs_buying_role",
            "hs_calculated_form_submissions",
            "hs_calculated_merged_vids",
            "hs_calculated_mobile_number",
            "hs_calculated_phone_number",
            "hs_calculated_phone_number_area_code",
            "hs_calculated_phone_number_country_code",
            "hs_calculated_phone_number_region_code",
            "hs_clicked_linkedin_ad",
            "hs_content_membership_email",
            "hs_content_membership_email_confirmed",
            "hs_content_membership_follow_up_enqueued_at",
            "hs_content_membership_notes",
            "hs_content_membership_registered_at",
            "hs_content_membership_registration_domain_sent_to",
            "hs_content_membership_registration_email_sent_at",
            "hs_content_membership_status",
            "hs_conversations_visitor_email",
            "hs_count_is_unworked",
            "hs_count_is_worked",
            "hs_created_by_conversations",
            "hs_created_by_user_id",
            "hs_createdate",
            "hs_date_entered_customer",
            "hs_date_entered_evangelist",
            "hs_date_entered_lead",
            "hs_date_entered_marketingqualifiedlead",
            "hs_date_entered_opportunity",
            "hs_date_entered_other",
            "hs_date_entered_salesqualifiedlead",
            "hs_date_entered_subscriber",
            "hs_date_exited_customer",
            "hs_date_exited_evangelist",
            "hs_date_exited_lead",
            "hs_date_exited_marketingqualifiedlead",
            "hs_date_exited_opportunity",
            "hs_date_exited_other",
            "hs_date_exited_salesqualifiedlead",
            "hs_date_exited_subscriber",
            "hs_document_last_revisited",
            "hs_email_bad_address",
            "hs_email_customer_quarantined_reason",
            "hs_email_domain",
            "hs_email_hard_bounce_reason",
            "hs_email_hard_bounce_reason_enum",
            "hs_email_quarantined",
            "hs_email_quarantined_reason",
            "hs_email_recipient_fatigue_recovery_time",
            "hs_email_sends_since_last_engagement",
            "hs_emailconfirmationstatus",
            "hs_facebook_ad_clicked",
            "hs_facebook_click_id",
            "hs_facebookid",
            "hs_feedback_last_nps_follow_up",
            "hs_feedback_last_nps_rating",
            "hs_feedback_last_survey_date",
            "hs_feedback_show_nps_web_survey",
            "hs_first_engagement_object_id",
            "hs_first_subscription_create_date",
            "hs_google_click_id",
            "hs_googleplusid",
            "hs_has_active_subscription",
            "hs_ip_timezone",
            "hs_is_contact",
            "hs_is_unworked",
            "hs_last_sales_activity_date",
            "hs_last_sales_activity_timestamp",
            "hs_last_sales_activity_type",
            "hs_lastmodifieddate",
            "hs_latest_sequence_ended_date",
            "hs_latest_sequence_enrolled",
            "hs_latest_sequence_enrolled_date",
            "hs_latest_sequence_finished_date",
            "hs_latest_sequence_unenrolled_date",
            "hs_latest_source_timestamp",
            "hs_latest_subscription_create_date",
            "hs_lead_status",
            "hs_legal_basis",
            "hs_linkedin_ad_clicked",
            "hs_linkedinid",
            "hs_marketable_reason_id",
            "hs_marketable_reason_type",
            "hs_marketable_status",
            "hs_marketable_until_renewal",
            "hs_merged_object_ids",
            "hs_object_id",
            "hs_pinned_engagement_id",
            "hs_pipeline",
            "hs_predictivecontactscore_v2",
            "hs_predictivescoringtier",
            "hs_read_only",
            "hs_sa_first_engagement_date",
            "hs_sa_first_engagement_descr",
            "hs_sa_first_engagement_object_type",
            "hs_sales_email_last_clicked",
            "hs_sales_email_last_opened",
            "hs_searchable_calculated_international_mobile_number",
            "hs_searchable_calculated_international_phone_number",
            "hs_searchable_calculated_mobile_number",
            "hs_searchable_calculated_phone_number",
            "hs_sequences_actively_enrolled_count",
            "hs_sequences_enrolled_count",
            "hs_sequences_is_enrolled",
            "hs_testpurge",
            "hs_testrollback",
            "hs_time_between_contact_creation_and_deal_close",
            "hs_time_between_contact_creation_and_deal_creation",
            "hs_time_in_customer",
            "hs_time_in_evangelist",
            "hs_time_in_lead",
            "hs_time_in_marketingqualifiedlead",
            "hs_time_in_opportunity",
            "hs_time_in_other",
            "hs_time_in_salesqualifiedlead",
            "hs_time_in_subscriber",
            "hs_time_to_first_engagement",
            "hs_time_to_move_from_lead_to_customer",
            "hs_time_to_move_from_marketingqualifiedlead_to_customer",
            "hs_time_to_move_from_opportunity_to_customer",
            "hs_time_to_move_from_salesqualifiedlead_to_customer",
            "hs_time_to_move_from_subscriber_to_customer",
            "hs_timezone",
            "hs_twitterid",
            "hs_unique_creation_key",
            "hs_updated_by_user_id",
            "hs_user_ids_of_all_notification_followers",
            "hs_user_ids_of_all_notification_unfollowers",
            "hs_user_ids_of_all_owners",
            "hs_whatsapp_phone_number",
            "hubspot_owner_assigneddate",
            "id_app",
            "id_de_jotform",
            "id_monday",
            "identificacion_contable_del_cliente",
            "ingresar_numero_de_cupon",
            "institucion_hijo_1",
            "institucion_hijo_2",
            "institucion_hijo_3",
            "institucion_hijo_4",
            "institucion_hijo_5",
            "ip_city",
            "ip_country",
            "ip_country_code",
            "ip_latlon",
            "ip_state",
            "ip_state_code",
            "ip_zipcode",
            "it_email",
            "job_function",
            "justificante_consignacion_fcje_r__de_alzada",
            "justificante_memorandum_administrativo",
            "justificante_ministerio_justicia__intencion_de_subsanar_",
            "justificante_ministerio_justicia__subsanado_",
            "justificante_recurso_de_alzada",
            "justificante_resguardo_de_presentacion",
            "justificante_subsanacion_de_vinculaciones",
            "justificante_subsanacion_por_consanguinidad",
            "last_aircall_call_outcome",
            "last_used_aircall_phone_number",
            "last_used_aircall_tags",
            "lastmodifieddate",
            "linaje",
            "linea_de_venezuela",
            "literal_de_nacimiento_conyuge_espanol",
            "marital_status",
            "mensaje_chat_bot",
            "military_status",
            "n000__referido_por__clonado_",
            "n1__aacs_introducido_asociacion",
            "n1__acta_notarial",
            "n1__f__peticion_por_genealogia",
            "n1__f__solicitado_por_genealogia",
            "n2__aacs_notificacion_aprobado",
            "n2__aiv_notificacion_aprobado",
            "n2__f__de_solicitud_al_cliente",
            "n2__f_solicitud_mayor_info",
            "n2__fecha_aacs_notificacion_aprobado",
            "n3__estatus_de_nacionalidad",
            "n3__f___recordatorio_filiacion",
            "n3__fcje_registro",
            "n3__fecha_de_recordatorio",
            "n4__aacs_retirado_asociacion",
            "n4__f__entregado_genealogia",
            "n4__f__enviada_a_genealogia",
            "n4__fcje_certifi__descargado",
            "n4__otros_nombres",
            "n5__fecha_de_registro",
            "n6__aacs_recibido_en_espana",
            "n6__aiv_recibido_en_espana",
            "nacionalidad_solicitada",
            "no_de_iban",
            "no_de_identificacion_nacional__nif_",
            "nombre_completo_del_conyuge",
            "nombre_completo_del_tutor_o_representante_legal",
            "nombre_en_pasaporte",
            "nombre_evento",
            "nombre_hijo_1",
            "nombre_hijo_2",
            "nombre_hijo_3",
            "nombre_hijo_4",
            "nombre_hijo_5",
            "nombre_jotform",
            "nombre_miembro_de_familia_1",
            "nombre_miembro_de_familia_2",
            "nombre_miembro_de_familia_3",
            "nombre_miembro_de_familia_4",
            "nombre_miembro_de_familia_5",
            "nombre_responsable__pago",
            "nombres_y_apellidos_de_madre",
            "nombres_y_apellidos_del_padre",
            "num_associated_deals",
            "num_conversion_events",
            "num_unique_conversion_events",
            "numero",
            "numero_de_adultos",
            "numero_de_expediente",
            "numero_de_ninos",
            "numero_de_pasaporte",
            "numero_de_pasaporte_del_tutor_o_representante_legal",
            "numero_pasaporte_responsable_pago",
            "opt_in_emails_de_marketing",
            "otros_servicios___mg_tours",
            "pagos_completos",
            "pagos_incompletos",
            "pais_de_expedicion_del_pasaporte",
            "pais_de_nacimiento",
            "pais_de_residencia",
            "pais_destino",
            "pais_origen",
            "pais_ubicacion_mg_tours",
            "paises_donde_ha_residido_en_los_ultimos_5_anos",
            "partida_de_matrimonio_apostillada",
            "partida_de_matrimonio_compulsada",
            "partida_de_matrimonio_de_padres",
            "partida_de_matrimonio_espana",
            "partida_de_matrimonio_simple",
            "partida_de_matrimonio_simple__",
            "partida_de_nacimiento_apostillada",
            "partida_de_nacimiento_compulsada",
            "partida_de_nacimiento_en_formato_digital",
            "partida_de_nacimiento_hijos_menores",
            "partida_de_nacimiento_madre_apostillada",
            "partida_de_nacimiento_padre_apostillada",
            "partida_de_nacimiento_simple",
            "partida_de_nacimiento_simple__",
            "partida_nacimiento_en_espana",
            "pasaporte__documento_",
            "pasaporte_apostillado",
            "pasaporte_completo",
            "pasaporte_compulsado",
            "pasaporte_habla_no_hispana",
            "pasaporte_nacionalidad",
            "pasaporte_simple",
            "pasaporte_simple_o_cedula",
            "persona_encargada_de_la_toma_de_decisiones",
            "plantilla_interna_carta_de_naturaleza",
            "plantilla_interna_del_ministerio",
            "poder_apostillado",
            "poder_compulsado",
            "poder_entre_tutores",
            "poder_general_para_pleitos",
            "poder_notariado_consulado",
            "poder_notariado_espana",
            "posee_otra_nacionalidad_ademas_de_la_venezolana_",
            "print_screen",
            "prioridad",
            "profesion",
            "provincia",
            "recent_conversion_date",
            "recent_conversion_event_name",
            "recent_deal_amount",
            "recent_deal_close_date",
            "recurso_alzada__notificacion_1_",
            "recurso_alzada__notificacion_2_",
            "referido_por",
            "referido_por_",
            "referidopor",
            "registro_cupon",
            "registro_de_nacimiento",
            "registro_pago",
            "relationship_status",
            "requerimientos_de_viaje",
            "requiere_tutor_o_representante_legal_",
            "resguardo_ccse",
            "resguardo_dele",
            "resolucion_denegatoria",
            "retirado",
            "school",
            "sefar_score",
            "seniority",
            "servicio_solicitado",
            "servicio_solicitado___gestion_documental",
            "servicio_solicitado__contacto_",
            "servicio_solicitado__otros_",
            "servicio_traduccion_ap__sefar_",
            "si_no",
            "sin_cita_ccse",
            "start_date",
            "stripe_form__transaction_id",
            "subsanacion__notificacion_1_",
            "subsanacion__notificacion_2_",
            "subsanacion__notificacion_3_",
            "sugerencias_mg_tours",
            "supervisor_de_ventas",
            "teamleader_id",
            "test_hs_monto",
            "tiene_algun_familiar_con_certificado_de_la_fcje_",
            "tiene_algun_familiar_que_este_o_haya_realizado_algun_proceso_con_nosotros_",
            "tiene_alguno_de_estos_familiares_con_nacionalidad_espanola_o_de_un_pais_de_la_ue_",
            "tiene_antepasados_espanoles",
            "tiene_antepasados_italianos",
            "tiene_hijos_",
            "tiene_residencia_permanente_en_algun_otro_pais_",
            "tienes_hijos_menores_de_18_anos_",
            "tipo_de_pasajero",
            "tipo_de_servicio_mg_tours",
            "tipo_de_visa_a_solicitar",
            "total_a_facturar",
            "total_revenue",
            "transaction_id",
            "utm_campaign",
            "utm_medium",
            "utm_source",
            "veces_casado",
            "vinculacion_1",
            "vinculacion_2",
            "vinculacion_3",
            "vinculo_antepasados",
            "vinculo_miembro_de_familia_1",
            "vinculo_miembro_de_familia_2",
            "vinculo_miembro_de_familia_3",
            "vinculo_miembro_de_familia_4",
            "vinculo_miembro_de_familia_5",
            "whatsapp",
            "whatsappeitor",
            "work_email",
            "ya_hiciste_tu_solicitud_de_nacionalidad_",
            "zoom_webinar_attendance_average_duration",
            "zoom_webinar_attendance_count",
            "zoom_webinar_joinlink",
            "zoom_webinar_registration_count",
            "firstname",
            "hs_analytics_first_url",
            "hs_email_delivered",
            "hs_email_optout_14133196",
            "hs_email_optout_16158875",
            "hs_email_optout_17511988",
            "twitterhandle",
            "currentlyinworkflow",
            "followercount",
            "hs_analytics_last_url",
            "hs_email_open",
            "lastname",
            "hs_analytics_num_page_views",
            "hs_email_click",
            "salutation",
            "twitterprofilephoto",
            "email",
            "hs_analytics_num_visits",
            "hs_email_bounce",
            "hs_persona",
            "hs_social_last_engagement",
            "hs_analytics_num_event_completions",
            "hs_email_optout",
            "hs_social_twitter_clicks",
            "mobilephone",
            "phone",
            "fax",
            "hs_analytics_first_timestamp",
            "hs_email_last_email_name",
            "hs_email_last_send_date",
            "hs_social_facebook_clicks",
            "address",
            "engagements_last_meeting_booked",
            "engagements_last_meeting_booked_campaign",
            "engagements_last_meeting_booked_medium",
            "engagements_last_meeting_booked_source",
            "hs_analytics_first_visit_timestamp",
            "hs_email_last_open_date",
            "hs_latest_meeting_activity",
            "hs_sales_email_last_replied",
            "hs_social_linkedin_clicks",
            "hubspot_owner_id",
            "notes_last_contacted",
            "notes_last_updated",
            "notes_next_activity_date",
            "num_contacted_notes",
            "num_notes",
            "owneremail",
            "ownername",
            "surveymonkeyeventlastupdated",
            "webinareventlastupdated",
            "city",
            "hs_analytics_last_timestamp",
            "hs_email_last_click_date",
            "hs_social_google_plus_clicks",
            "hubspot_team_id",
            "linkedinbio",
            "twitterbio",
            "hs_all_owner_ids",
            "hs_analytics_last_visit_timestamp",
            "hs_email_first_send_date",
            "hs_social_num_broadcast_clicks",
            "state",
            "hs_all_team_ids",
            "hs_analytics_source",
            "hs_email_first_open_date",
            "hs_latest_source",
            "zip",
            "country",
            "hs_all_accessible_team_ids",
            "hs_analytics_source_data_1",
            "hs_email_first_click_date",
            "hs_latest_source_data_1",
            "linkedinconnections",
            "hs_analytics_source_data_2",
            "hs_email_is_ineligible",
            "hs_language",
            "hs_latest_source_data_2",
            "kloutscoregeneral",
            "hs_analytics_first_referrer",
            "hs_email_first_reply_date",
            "jobtitle",
            "photo",
            "hs_analytics_last_referrer",
            "hs_email_last_reply_date",
            "message",
            "closedate",
            "hs_analytics_average_page_views",
            "hs_email_replied",
            "hs_analytics_revenue",
            "hs_lifecyclestage_lead_date",
            "hs_lifecyclestage_marketingqualifiedlead_date",
            "hs_lifecyclestage_opportunity_date",
            "lifecyclestage",
            "hs_lifecyclestage_salesqualifiedlead_date",
            "createdate",
            "hs_lifecyclestage_evangelist_date",
            "hs_lifecyclestage_customer_date",
            "hubspotscore",
            "company",
            "hs_lifecyclestage_subscriber_date",
            "hs_lifecyclestage_other_date",
            "website",
            "numemployees",
            "annualrevenue",
            "industry",
            "associatedcompanyid",
            "associatedcompanylastupdated",
            "hs_predictivecontactscorebucket",
            "hs_predictivecontactscore",
        ]);

        //Hago la busqueda del cliente
        $contactHS = $hubspot->crm()->contacts()->searchApi()->doSearch($searchRequest);

        //sago solo el id del contacto:
        $idcontact = $contactHS['results'][0]['id'];

        /*

                DEALS

        */

        // Asignar el id del cliente en Hubspot para el query

        $publicObjectId1 = new PublicObjectId([
            'id' => $idcontact
        ]);

        $batchInputPublicObjectId = new BatchInputPublicObjectId([
            'inputs' => [$publicObjectId1],
        ]);

        //Obtener los Deals del Cliente

        $dealIdsHS = $hubspot->crm()->associations()->batchApi()->read('contact', 'deal', $batchInputPublicObjectId);

        //Crear arreglo donde se van a almacenar los Deals de los clientes (puede haber mas de uno)

        $dealsData = [];

        //Propiedades de los Deals de Hubspot

        $arreglo_propiedades = [
            "a_p_nacimiento_apostillado_traducido",
            "a_p_residencia_apostillado_traducido",
            "a_p_simple_apostillados_nacimiento",
            "a_p_simple_apostillados_residencia_1e",
            "a_p_simple_apostillados_residencia_2",
            "acta_defuncion_conyuge_apostillada",
            "amount_in_home_currency",
            "analista_de_ventas",
            "anexo_i",
            "anexo_ii",
            "anexo_iii",
            "anexo_v",
            "apellidos_del_cliente",
            "argumento_de_ventas__new_",
            "autorizacion___cuestionario",
            "autorizacion_memorandum_administrativo",
            "autorizacion_recurso_de_alzada",
            "autorizacion_subsanacion",
            "carta_motivada",
            "carta_nat_pagado",
            "carta_nat_preestab",
            "certificacion_academica_estudios",
            "certificacion_laboral",
            "certificado_ccse",
            "cliente__numero",
            "codigo_de_consulta_ministerio",
            "constancia_membresia_asociaciones_gremiales__si_aplica_",
            "constancia_nacimiento_simple_apostillada",
            "contenido_pragmatico_apostillado",
            "cualificacion_profesional",
            "cuestionario",
            "days_to_close",
            "deal_currency_code",
            "declaracion_cil",
            "declaracion_emolumentos",
            "declaracion_inscripcion_de_nacimiento",
            "declaracion_jurada_de_residencia",
            "df_billing_contact",
            "df_currency",
            "df_discount_code",
            "df_mode",
            "df_order_id",
            "df_payment_method",
            "df_payment_processor",
            "df_payment_status",
            "df_payment_type",
            "df_product_name",
            "df_quantity",
            "df_source",
            "df_subscription_frequency",
            "df_tax_amount",
            "df_tax_rate",
            "diligencias_de_ordenacion",
            "dni_progenitores",
            "documentacion_ejercicio_profesional",
            "documentacion_fase_1_solicitada",
            "documentacion_fase_2_solicitada",
            "documentacion_fase_3_solicitada",
            "documento_de_acreditacion_de_nacionalidad",
            "documentos",
            "enlace_proyecto__documentacion_",
            "estatus__genealogia",
            "etiquetas",
            "fase_0_pagado",
            "fase_1_pagado",
            "fase_1_preestab",
            "fase_2_pagado",
            "fase_2_preestab",
            "fase_3_pagado",
            "fase_3_preestab",
            "fcje",
            "fcje_pagado",
            "fcje_preestab",
            "fecha_de_aceptacion",
            "fecha_de_cobro",
            "fecha_de_confirmacion_del_cliente_como_recibido__juramentacion_",
            "fecha_de_nacimiento",
            "fecha_de_notificacion_al_cliente_por_email__juramentacion_",
            "fecha_de_rechazo",
            "fecha_de_resolucion",
            "fecha_en_la_que_se_anadio",
            "fecha_fase_0_pagado",
            "fecha_fase_1_pagado",
            "fecha_fase_2_pagado",
            "fecha_fase_3_pagado",
            "formalizacion_d__judicial",
            "formalizacion_r__alzada",
            "formulario_visa",
            "foto_visa",
            "fuente_teamleader",
            "genealogia_aprobada",
            "homologacion_de_titulo_de_otro_pais_apostillada__si_aplica_",
            "hs_acv",
            "hs_all_assigned_business_unit_ids",
            "hs_all_collaborator_owner_ids",
            "hs_all_deal_split_owner_ids",
            "hs_analytics_latest_source",
            "hs_analytics_latest_source_company",
            "hs_analytics_latest_source_contact",
            "hs_analytics_latest_source_data_1",
            "hs_analytics_latest_source_data_1_company",
            "hs_analytics_latest_source_data_1_contact",
            "hs_analytics_latest_source_data_2",
            "hs_analytics_latest_source_data_2_company",
            "hs_analytics_latest_source_data_2_contact",
            "hs_analytics_latest_source_timestamp",
            "hs_analytics_latest_source_timestamp_company",
            "hs_analytics_latest_source_timestamp_contact",
            "hs_analytics_source",
            "hs_analytics_source_data_1",
            "hs_analytics_source_data_2",
            "hs_arr",
            "hs_campaign",
            "hs_closed_amount",
            "hs_closed_amount_in_home_currency",
            "hs_created_by_user_id",
            "hs_date_entered_1077258",
            "hs_date_entered_1077260",
            "hs_date_entered_1077262",
            "hs_date_entered_1077264",
            "hs_date_entered_1077265",
            "hs_date_entered_1080083",
            "hs_date_entered_2923064",
            "hs_date_entered_2923065",
            "hs_date_entered_2923069",
            "hs_date_entered_2923070",
            "hs_date_entered_33873208",
            "hs_date_entered_33873209",
            "hs_date_entered_33873210",
            "hs_date_entered_33873213",
            "hs_date_entered_33873214",
            "hs_date_entered_429095",
            "hs_date_entered_429096",
            "hs_date_entered_429097",
            "hs_date_entered_429098",
            "hs_date_entered_429099",
            "hs_date_entered_429100",
            "hs_date_entered_429101",
            "hs_date_entered_45989641",
            "hs_date_entered_46040221",
            "hs_date_entered_53192618",
            "hs_date_entered_9037927",
            "hs_date_entered_9037928",
            "hs_date_entered_9037929",
            "hs_date_entered_9037930",
            "hs_date_entered_9037931",
            "hs_date_entered_9037932",
            "hs_date_entered_9037933",
            "hs_date_entered_appointmentscheduled",
            "hs_date_entered_closedlost",
            "hs_date_entered_closedwon",
            "hs_date_entered_contractsent",
            "hs_date_entered_decisionmakerboughtin",
            "hs_date_entered_presentationscheduled",
            "hs_date_entered_qualifiedtobuy",
            "hs_date_exited_1077258",
            "hs_date_exited_1077260",
            "hs_date_exited_1077262",
            "hs_date_exited_1077264",
            "hs_date_exited_1077265",
            "hs_date_exited_1080083",
            "hs_date_exited_2923064",
            "hs_date_exited_2923065",
            "hs_date_exited_2923069",
            "hs_date_exited_2923070",
            "hs_date_exited_33873208",
            "hs_date_exited_33873209",
            "hs_date_exited_33873210",
            "hs_date_exited_33873213",
            "hs_date_exited_33873214",
            "hs_date_exited_429095",
            "hs_date_exited_429096",
            "hs_date_exited_429097",
            "hs_date_exited_429098",
            "hs_date_exited_429099",
            "hs_date_exited_429100",
            "hs_date_exited_429101",
            "hs_date_exited_45989641",
            "hs_date_exited_46040221",
            "hs_date_exited_53192618",
            "hs_date_exited_9037927",
            "hs_date_exited_9037928",
            "hs_date_exited_9037929",
            "hs_date_exited_9037930",
            "hs_date_exited_9037931",
            "hs_date_exited_9037932",
            "hs_date_exited_9037933",
            "hs_date_exited_appointmentscheduled",
            "hs_date_exited_closedlost",
            "hs_date_exited_closedwon",
            "hs_date_exited_contractsent",
            "hs_date_exited_decisionmakerboughtin",
            "hs_date_exited_presentationscheduled",
            "hs_date_exited_qualifiedtobuy",
            "hs_deal_amount_calculation_preference",
            "hs_deal_stage_probability",
            "hs_deal_stage_probability_shadow",
            "hs_exchange_rate",
            "hs_forecast_amount",
            "hs_forecast_probability",
            "hs_is_closed",
            "hs_is_closed_won",
            "hs_is_deal_split",
            "hs_lastmodifieddate",
            "hs_likelihood_to_close",
            "hs_line_item_global_term_hs_discount_percentage",
            "hs_line_item_global_term_hs_discount_percentage_enabled",
            "hs_line_item_global_term_hs_recurring_billing_period",
            "hs_line_item_global_term_hs_recurring_billing_period_enabled",
            "hs_line_item_global_term_hs_recurring_billing_start_date",
            "hs_line_item_global_term_hs_recurring_billing_start_date_enabled",
            "hs_line_item_global_term_recurringbillingfrequency",
            "hs_line_item_global_term_recurringbillingfrequency_enabled",
            "hs_manual_forecast_category",
            "hs_merged_object_ids",
            "hs_mrr",
            "hs_next_step",
            "hs_num_associated_active_deal_registrations",
            "hs_num_associated_deal_registrations",
            "hs_num_associated_deal_splits",
            "hs_num_target_accounts",
            "hs_object_id",
            "hs_pinned_engagement_id",
            "hs_predicted_amount",
            "hs_predicted_amount_in_home_currency",
            "hs_priority",
            "hs_projected_amount",
            "hs_projected_amount_in_home_currency",
            "hs_read_only",
            "hs_tag_ids",
            "hs_tcv",
            "hs_time_in_1077258",
            "hs_time_in_1077260",
            "hs_time_in_1077262",
            "hs_time_in_1077264",
            "hs_time_in_1077265",
            "hs_time_in_1080083",
            "hs_time_in_2923064",
            "hs_time_in_2923065",
            "hs_time_in_2923069",
            "hs_time_in_2923070",
            "hs_time_in_33873208",
            "hs_time_in_33873209",
            "hs_time_in_33873210",
            "hs_time_in_33873213",
            "hs_time_in_33873214",
            "hs_time_in_429095",
            "hs_time_in_429096",
            "hs_time_in_429097",
            "hs_time_in_429098",
            "hs_time_in_429099",
            "hs_time_in_429100",
            "hs_time_in_429101",
            "hs_time_in_45989641",
            "hs_time_in_46040221",
            "hs_time_in_53192618",
            "hs_time_in_9037927",
            "hs_time_in_9037928",
            "hs_time_in_9037929",
            "hs_time_in_9037930",
            "hs_time_in_9037931",
            "hs_time_in_9037932",
            "hs_time_in_9037933",
            "hs_time_in_appointmentscheduled",
            "hs_time_in_closedlost",
            "hs_time_in_closedwon",
            "hs_time_in_contractsent",
            "hs_time_in_decisionmakerboughtin",
            "hs_time_in_presentationscheduled",
            "hs_time_in_qualifiedtobuy",
            "hs_unique_creation_key",
            "hs_updated_by_user_id",
            "hs_user_ids_of_all_notification_followers",
            "hs_user_ids_of_all_notification_unfollowers",
            "hs_user_ids_of_all_owners",
            "hubspot_owner_assigneddate",
            "id_del_contacto_asociado",
            "informe_juridico",
            "justificante_anuncio_demanda_judicial",
            "justificante_recurso_de_alzada",
            "justificante_subsanacion",
            "justificante_subsanacion_subsanacion",
            "mas_informacion",
            "monto_fase_1_pagado",
            "monto_fase_2_pagado",
            "monto_fase_3_pagado",
            "motivo_del_rechazo__explicacion_",
            "n10__fecha_asignacion_de_juez",
            "n10__justificante_de_formalizacion",
            "n10__justificante_de_formalizacion__enlace_",
            "n11__envio_redaccion_abogada",
            "n12__notas___no__expediente",
            "n1__enviada_al_cliente",
            "n1__fecha_de_registro_fcje",
            "n1__lugar_del_expediente",
            "n2__antecedentes_penales",
            "n2__ciudad_formalizacion",
            "n2__enviado_a_redaccion_informe",
            "n2__firmado_por_el_cliente",
            "n2__monto_pagado",
            "n3__contratos_y_permisos",
            "n3__f__vencimiento_ant__penal",
            "n3__gestionado___entregado",
            "n3__informe_cargado",
            "n4__certificado_descargado",
            "n4__f__venci__antec__penal_otro",
            "n4__notario___abogado",
            "n4__pago_tasa",
            "n5___f_solicitud_documentos",
            "n5__fecha_de_formalizacion",
            "n6__cil_preaprobado",
            "n6__f_venci_p_n_apostilla",
            "n6__fecha_acta_remitida_",
            "n7__enviado_al_dto_juridico",
            "n7__fecha_caducidad_pasaporte",
            "n7__fecha_de_resolucion",
            "n8__f_rec__solicitud_doc",
            "n9__notif__1__int__subsanar_",
            "no__pasaporte",
            "no_de_proyecto_tl",
            "nombre_cliente",
            "numero",
            "numero_de_telefono",
            "numero_de_telefono_movil",
            "origen",
            "p_m_apostillada",
            "p_m_apostillada_compulsada_y_traducida",
            "p_m_progenitores_simple_apostillada",
            "p_m_simple___apostillada__abuelos_",
            "p_m_simple___apostillada__bis_abuelos_",
            "p_m_simple__apostillada___padres_",
            "p_n_apostillada",
            "p_n_apostillada__padre_madre_",
            "p_n_apostillada__solicitante_",
            "p_n_apostillada_compulsada_y_traducida",
            "p_n_apostillada_inscripcion_fuera_de_plazo_espana",
            "p_n_espana_progenitores_del_inscrito",
            "p_n_espanola__abuela_",
            "p_n_espanola__abuelo_",
            "p_n_espanola__bis_abuelo_",
            "p_n_espanola__padre_madre_",
            "p_n_hijo_nieto_para_inscripcion",
            "p_n_simple",
            "p_n_simple_carta_de_naturaleza",
            "p_n_simple_hijos_menores_1",
            "p_n_simple_hijos_menores_2",
            "p_n_simple_hijos_menores_3",
            "p_n_simple_hijos_menores_4",
            "p_n_simple_ley_de_demoria_democratica",
            "pago_acumulado_por_negocio",
            "pago_cupon",
            "pago_registro",
            "partida_de_nacimiento_simple_apostillada",
            "partida_de_nacimiento_simple_apostillada_demanda_judicial",
            "partida_de_nacimiento_simple_apostillada_memorandum_administrativo",
            "partida_de_nacimiento_simple_apostillada_subsanacion",
            "pasaporte_apostillado_carta_de_naturaleza",
            "pasaporte_apostillado_compulsado",
            "pasaporte_compulsado",
            "pasaporte_del_solicitante__descendiente_de_espanol_",
            "pasaporte_espanol__abuelo_abuela_",
            "pasaporte_espanol__bisabuelo_bisabuela_",
            "pasaporte_espanol__padre_madre_",
            "pasaporte_espanol_progenitores",
            "pasaporte_simple",
            "pasaporte_simple_carta_de_naturaleza",
            "pasaporte_simple_demanda_judicial",
            "pasaporte_simple_ley_de_memoria_democratica",
            "pasaporte_simple_memorandum_administrativo",
            "pasaporte_simple_recursos_de_alzada",
            "pasaporte_simple_subsanacion",
            "pasaporte_simple_vigente",
            "pasaporte_simple_vigente_recurso_de_alzada",
            "planilla_de_datos_personales",
            "planilla_interna_carta_naturaleza",
            "pn_espanola__bis_abuela_",
            "poder_de_representacion_apostillado_legitimado",
            "poder_de_representacion_apostillado_notariado",
            "poder_de_representacion_compulsado_espana",
            "poder_de_representacion_en_consulado_de_espana",
            "poder_general_para_pleitos",
            "proyecto",
            "proyecto_tl",
            "representante_legal",
            "resolucion_denegatoria",
            "resolucion_estimatoria_nacionalidad",
            "se_trabaja_desde",
            "sentencia_de_divorcio_apostillada",
            "servicio_solicitado",
            "servicio_solicitado2",
            "supervisor_de_ventas",
            "titulo_universitario_apostillado",
            "vinculacion_1",
            "vinculacion_2",
            "vinculacion_3",
            "vinculacion_4",
            "visa_caducada",
            "dealname",
            "amount",
            "dealstage",
            "pipeline",
            "closedate",
            "createdate",
            "engagements_last_meeting_booked",
            "engagements_last_meeting_booked_campaign",
            "engagements_last_meeting_booked_medium",
            "engagements_last_meeting_booked_source",
            "hs_latest_meeting_activity",
            "hs_sales_email_last_replied",
            "hubspot_owner_id",
            "notes_last_contacted",
            "notes_last_updated",
            "notes_next_activity_date",
            "num_contacted_notes",
            "num_notes",
            "hs_createdate",
            "hubspot_team_id",
            "dealtype",
            "hs_all_owner_ids",
            "description",
            "hs_all_team_ids",
            "hs_all_accessible_team_ids",
            "num_associated_contacts",
            "closed_lost_reason",
            "closed_won_reason",
            "estatus_proceso"
        ];

        //Hago el llamado a Hubspot por cada deal que tenga el usuario... Seguramente se puede hacer el llamado a dos IDs distintos, pero no he revisado bien eso.

        if (isset($dealIdsHS['results'][0]['to'])){
            foreach ($dealIdsHS['results'][0]['to'] as $dealid){
                $dealsData[] = json_decode(json_encode($hubspot->crm()->deals()->basicApi()->getById($dealid['id'], $arreglo_propiedades , false)),true);
            }

            /*

                PIPELINES y DEALSTAGES

                Nota: el Pipeline que nos interesa, realmente, es el 94794, asi que solo deberiamos verificar si es el pipeline que aparece dentro de nuestro negocio abierto.

                Aqui solo hace el query del DealStage para obtener el porcentaje del proceso, y solo añade eso al arreglo.

            */

            foreach ($dealsData as $key => $deal){
                if ($deal["properties"]["pipeline"] == 94794 || $deal["properties"]["pipeline"] == "94794"){
                    $dealsData[$key]["dealstage"] = json_decode(json_encode($hubspot->crm()->pipelines()->pipelineStagesApi()->getById('deal', '94794', $deal["properties"]["dealstage"])),true);
                }
            }
        }

        return view('crud.users.status', compact('user', 'contactHS', 'dealsData', 'servicioHS', 'referidosHS', 'familiaresR', 'usuario_mdy'));
    }

    protected function getHubspotData($hsId)
    {
        // Ejecutar llamadas concurrentes
        $result = $this->hubspotService->executeConcurrent([
            'contact' => fn() => $this->hubspotService->getContactByIdPromise($hsId),
            'files' => fn() => $this->hubspotService->getEngagementsByContactIdPromise($hsId),
            'urls' => fn() => $this->hubspotService->getContactFileFieldsPromise($hsId),
            'deals' => fn() => $this->hubspotService->getDealsByContactIdPromise($hsId)
        ]);

        return $result;
    }

    protected function syncUserWithHubspot($user)
    {
        $HScontact = $this->hubspotService->searchContactByEmail($user->email)
                ?? $this->hubspotService->searchContactByPassport($user->passport);

        if (!$HScontact) {
            throw new \Exception("El contacto no se encontró en HubSpot ni por email ni por pasaporte.");
        }

        $user->update([
            'hs_id' => $HScontact['id'],
            'email' => $HScontact['properties']['email'] ?? $user->email,
            'nombres' => $HScontact['properties']['firstname'] ?? $user->nombres,
            'apellidos' => $HScontact['properties']['lastname'] ?? $user->apellidos
        ]);
    }



    /**
     * Procesa archivos de forma concurrente desde URLs de HubSpot
     *
     * @param array $urls Array de URLs a procesar
     * @param mixed $user Objeto del usuario
     * @param HubspotService $hubspotService Instancia del servicio
     * @return array Archivos procesados exitosamente
     */
    private function processFilesConcurrently(array $urls, $user, HubspotService $hubspotService): array
    {
        // 1. Configuración inicial
        $client = new Client(['timeout' => 15]);
        $processedFiles = [];

        // 2. Obtener archivos existentes en una sola consulta
        $existingFiles = File::where('IDCliente', $user->passport)
            ->pluck('file')
            ->toArray();

        // 3. Preparar promesas para descargas concurrentes
        $promises = [];
        $validFiles = [];

        foreach ($urls as $url) {
            try {
                $fileUrl = $hubspotService->getFileUrlFromFormIntegrations($url);
                if (!$fileUrl) continue;

                $filename = basename(parse_url($fileUrl, PHP_URL_PATH));
                $s3Path = "public/doc/{$user->passport}/{$filename}";

                // Verificar si ya existe
                if (in_array($filename, $existingFiles)) {
                    continue;
                }
                if (Storage::disk('s3')->exists($s3Path)) {
                    continue;
                }

                $validFiles[$filename] = $s3Path;
                $promises[$filename] = $client->getAsync($fileUrl);

            } catch (\Exception $e) {
                \Log::error("Error preparando descarga: {$e->getMessage()}");
            }
        }

        // 4. Ejecutar descargas concurrentes
        $responses = Promise\Utils::settle($promises)->wait();

        // 5. Procesar resultados
        foreach ($responses as $filename => $response) {
            if ($response['state'] !== 'fulfilled') {
                \Log::warning("Fallo descarga: {$filename}");
                continue;
            }

            try {
                $fileContent = $response['value']->getBody();
                $s3Path = $validFiles[$filename];

                // Subir a S3
                Storage::disk('s3')->put($s3Path, $fileContent);

                // Registrar en DB
                File::create([
                    'file' => $filename,
                    'location' => "public/doc/{$user->passport}/",
                    'IDCliente' => $user->passport,
                ]);

                $processedFiles[] = $filename;

            } catch (\Exception $e) {
                \Log::error("Error procesando {$filename}: {$e->getMessage()}");
            }
        }

        return $processedFiles;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(User $user)
    {
        // Ruta completa a la carpeta dentro de public
        $path = public_path('img/IMAGENESCOS/');

        // Obtener todos los archivos del directorio
        $files = scandir($path);

        // Filtrar solo imágenes
        $images = array_filter($files, function($file) {
            return preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file);
        });

        $images = array_values($images);

        // Mezclar las imágenes aleatoriamente
        shuffle($images);

        // Si necesitas las rutas completas:
        $imagePaths = array_map(function($image) use ($path) {
            return $path . '/' . $image;
        }, $images);

        // Si necesitas las URLs accesibles desde web:
        $imageUrls = array_map(function($image) {
            return asset('img/IMAGENESCOS/' . $image);
        }, $images);

        $cos = array_cos();

        $usuariosMonday = $this->getUsersForSelect()->original ?? [];

        $facturas = Factura::with('compras')->where('id_cliente', $user->id)->get();

        if (is_null($user->hs_id)) {
            $this->syncUserWithHubspot($user);
        }

        $hubspotData = $this->getHubspotData($user->hs_id);

        $HScontact = $hubspotData['contact'];
        $HScontactFiles = $hubspotData['files'];
        $urls = $hubspotData['urls'];
        $deals = $hubspotData['deals'];

        $camposDeTeamleader = [
            'n1__enviada_al_cliente' => '4203d8ab-f1de-0145-af52-1bb278951268',
            'documentos' => 'e254d7ed-3c93-097d-b659-852a3b74c5e5',
            'n1__lugar_del_expediente' => '4bbfdc08-686d-0a03-8557-bd1d60d46f57',
            'n1__monto_preestablecido' => '6f7a4408-b146-0e58-a35b-8f02fed60887',
            'n10__fecha_asignacion_de_juez' => '497e7359-8b1a-056a-9e5e-28fa0cf5b2f1',
            'n11__envio_redaccion_abogada' => 'e04af721-8808-0a43-9356-df374565b2fa',
            'n12__notas___no__expediente' => '4b822322-17a1-06ba-9b5b-82db70f46f5b',
            'n13__fecha_recurso_alzada' => '7c06cfed-87f4-00ac-8a5a-946b0b9643b8',
            'n2__firmado_por_el_cliente' => '5f090e48-4a5b-0504-8259-9e945e95126a',
            'n2__antecedentes_penales' => '35c68020-1160-068b-b055-1b5e6fe4ca11',
            'n2__ciudad_formalizacion' => 'ad849a21-82b3-0032-995e-6e9dbcd46f53',
            'n2__enviado_a_redaccion_informe' => 'ed8167e1-00e2-05fb-8a5c-900699b54d88',
            'n2__monto_pagado' => '4bef5482-f2e4-02da-8653-691944760f84',
            'n3__gestionado___entregado' => 'b0421965-2b39-0c4d-9e51-1e567b05126b',
            'n3__contratos_y_permisos' => '39085084-d206-073e-8057-ef23ab046f5a',
            'n3__f__vencimiento_ant__penal' => '578e17da-c01b-0a97-bc5a-7d9255b4c9d5',
            'n3__informe_cargado' => '1c067d8e-1b3b-0b4b-8c5f-436233b4c3f2',
            'n4__certificado_descargado' => '62a2cd97-1898-00bf-885c-029939e4c40f',
            'n4__pago_tasa' => 'a2d11316-e31b-0b2c-bd5e-0c7ad13491d0',
            'n5___f_solicitud_documentos' => 'e0919d4b-322a-0c06-9759-0a6607f4c9db',
            'n5__fecha_de_formalizacion' => '7c87a75b-ce63-01da-9c58-5277f6c40fa9',
            'n5__notas_genealogia' => 'edc41efc-e52f-0c9a-8e5d-41b8fff4c3f3',
            'n6__cil_preaprobado' => '57535be4-4738-00b5-9251-b53739e607c0',
            'n6__fecha_acta_remitida_' => '8091a7fc-3023-0625-8051-de85a4c46f59',
            'n7__enviado_al_dto_juridico' => 'c3feeebf-21a9-0cac-855e-e6f550260ee0',
            'n7__fecha_caducidad_pasaporte' => '6fb8ef4e-6fdb-0241-8354-bda543e4cbff',
            'n7__fecha_de_resolucion' => '3ef52253-5ac1-025a-8c5b-a9d094c468b8',
            'n8__f_rec__solicitud_doc' => 'e255a259-5328-0ee6-ab52-3e4f9604c9de',
            'n9__enviado_a_legales' => '047dc070-6b23-0434-b858-61a1d7e4c9fd',
            'n9__notif__1__int__subsanar_' => '7918f47c-4097-07e1-af57-d6c435660883',
            'n91__recepcion_recaudos_fisico' => '8e8ea98b-5137-047b-8157-c44935a4c3f1',
            'carta_nat_pagado' => '4339375f-ed77-02d9-a157-7da9f9e4bfac',
            'carta_nat_preestab' => 'a42ed217-b570-0973-9052-fab97214c229',
            'cil___fcje_pagado' => 'f23fbe3b-5d13-0a41-a857-e9ab1c63dc42',
            'cil___fcje_preestab' => 'aa1ce4b9-a410-00f2-a953-5f8c2713dc35',
            'codigo_de_proceso' => 'a42f63f5-d527-0544-ab50-9c03857707f2',
            'argumento_de_ventas__new_' => 'c34c71b3-331e-0524-a45a-95a654e51b4c',
            'fase_0_pagado__teamleader_' => 'd90b2e44-2e9b-0f29-945a-71c34bb3def0',
            'fase_1_pagado__teamleader_' => 'a1b50c58-8175-0d13-9856-f661e783dc08',
            'fase_1_preestab' => '73173887-a0e8-0f4f-bb55-b61f33d3c6e9',
            'fase_2_pagado__teamleader_' => 'a5b94ccc-3ea8-06fc-b259-0a487073dc0d',
            'fase_2_preestab' => 'c66a9c15-c965-0812-ad5b-7e48f183c6f9',
            'fase_3_pagado__teamleader_' => '9a1df9b7-c92f-09e5-b156-96af3f83dc0e',
            'fase_3_preestab' => 'e41fdbbb-a25a-005b-af56-9f3ca623c700',
            'fecha_de_aceptacion' => 'fbe8df81-7225-0c01-b051-7f1032054ffe',
            'servicio_solicitado2' => 'fcd48891-20f6-049a-a05f-f78a6f951b4d'
        ];

        if (is_null($user->tl_id)) {
            try {
                $TLcontactByEmail = $this->teamleaderService->searchContactByEmail($user->email);

                if (!is_null($TLcontactByEmail)) {
                    $user->tl_id = $TLcontactByEmail['id'];
                } else {
                    $newContact = $this->teamleaderService->createContact($user);
                    $user->tl_id = $newContact['id'];
                }

                $user->save();
            } catch (\Exception $e) {
                \Log::error("Error al sincronizar con Teamleader: " . $e->getMessage());
            }
        }

        // 2. Obtener datos en paralelo
        try {
            $concurrentResults = $this->hubspotService->executeConcurrent([
                'pipelineStages' => function() use ($deals) {
                    $usedPipelineIds = array_unique(array_filter(
                        array_column(array_column($deals, 'properties'), 'pipeline')
                    ));
                    $stages = [];
                    foreach ($usedPipelineIds as $pipelineId) {
                        $stages[$pipelineId] = $this->hubspotService->getDealStagesByPipeline($pipelineId);
                    }
                    return $stages;
                },
                'TLcontact' => function() use ($user) {
                    return $this->teamleaderService->getContactById($user->tl_id);
                },
                'TLdeals' => function() use ($user) {
                    return $this->teamleaderService->getProjectsWithDetailsByCustomerId($user->tl_id);
                }
            ]);

            $pipelineStages = $concurrentResults['pipelineStages'] ?? [];
            $TLcontact = $concurrentResults['TLcontact'] ?? null;
            $TLdeals = $concurrentResults['TLdeals'] ?? [];

            // 3. Procesar negocios con etapas
            $dealsWithStages = array_map(function ($deal) use ($pipelineStages) {
                $properties = $deal['properties'];
                $dealstageId = $properties['dealstage'] ?? null;
                $pipelineId = $properties['pipeline'] ?? null;

                $dealstageName = null;
                $dealstageOptions = [];

                if ($pipelineId && isset($pipelineStages[$pipelineId])) {
                    $dealstageName = collect($pipelineStages[$pipelineId])->firstWhere('id', $dealstageId)['name'] ?? null;
                    $dealstageOptions = $pipelineStages[$pipelineId];
                }

                return array_merge($deal, [
                    'dealstage_name' => $dealstageName,
                    'dealstage_options' => $dealstageOptions,
                ]);
            }, $deals);

            // 4. Sincronizar tratos con Teamleader
            $teamleaderDealNames = [];
            if (is_array($TLdeals)) {
                $teamleaderDealNames = array_column($TLdeals, 'title');
            }

            if (count($dealsWithStages) > count($teamleaderDealNames)) {
                foreach ($dealsWithStages as $deal) {
                    $dealName = $deal['properties']['dealname'] ?? '';
                    if (!in_array($dealName, $teamleaderDealNames)) {
                        try {
                            $this->teamleaderService->createProjectFromHubspotDeal(
                                $deal,
                                $user->tl_id,
                                $camposDeTeamleader
                            );
                        } catch (\Exception $e) {
                            \Log::error("Error al crear proyecto en Teamleader: " . $e->getMessage());
                        }
                    }
                }

                // Actualizar lista de tratos después de las creaciones
                $TLdeals = $this->teamleaderService->getProjectsWithDetailsByCustomerId($user->tl_id);
            }

            // 5. Obtener datos actualizados
            $updatedDeals = $this->hubspotService->getDealsByContactId($user->hs_id);
            $updatedTLdeals = $this->teamleaderService->getProjectsWithDetailsByCustomerId($user->tl_id);

            $this->syncDealFieldsBetweenPlatforms($updatedDeals, $updatedTLdeals, $camposDeTeamleader, $user);

            $teamleaderDealNames = [];
            if (is_array($updatedTLdeals)) {
                $teamleaderDealNames = array_column($updatedTLdeals, 'title', 'id');
            }

            // 6. Procesar propiedades y actualizar base de datos
            $columns = Schema::getColumnListing((new Negocio)->getTable());
            $excludedColumns = ['id', 'created_at', 'updated_at', 'hubspot_id', 'teamleader_id', 'user_id'];
            $fillableColumns = array_diff($columns, $excludedColumns);

            $processProperty = function($value) {
                if (is_null($value)) return null;
                $arrayData = strpos($value, ';') !== false ? explode(';', $value) : [$value];
                return json_encode($arrayData, JSON_UNESCAPED_UNICODE);
            };

            // Obtener deals existentes indexados por hubspot_id
            $existingDeals = Negocio::whereIn('hubspot_id', array_column($updatedDeals, 'id'))
                ->get()
                ->keyBy('hubspot_id');

            $newDeals = [];
            $dealsToUpdate = [];

            foreach ($updatedDeals as $deal) {
                // Procesar propiedades especiales
                $propsToProcess = ['argumento_de_ventas__new_', 'n2__antecedentes_penales', 'documentos'];
                foreach ($propsToProcess as $prop) {
                    if (isset($deal['properties'][$prop])) {
                        $deal['properties'][$prop] = $processProperty($deal['properties'][$prop]);
                    }
                }

                // Preparar datos comunes
                $data = [
                    'dealname' => $deal['properties']['dealname'] ?? null,
                    'teamleader_id' => array_search($deal['properties']['dealname'] ?? null, $teamleaderDealNames) ?: null,
                ];

                foreach ($fillableColumns as $column) {
                    $data[$column] = $deal['properties'][$column] ?? null;
                }

                // Verificar si el deal existe
                if ($existingDeals->has($deal['id'])) {
                    // Actualizar deal existente
                    $existingDeal = $existingDeals->get($deal['id']);

                    // Solo actualizar si hay cambios
                    $hasChanges = false;
                    foreach ($data as $key => $value) {
                        if ($existingDeal->{$key} != $value) {
                            $hasChanges = true;
                            break;
                        }
                    }

                    if ($hasChanges) {
                        $dealsToUpdate[] = [
                            'id' => $existingDeal->id,
                            'data' => $data
                        ];
                    }
                } else {
                    // Insertar nuevo deal
                    $newDeals[] = array_merge([
                        'hubspot_id' => $deal['id'],
                        'user_id' => $user->id,
                    ], $data);
                }
            }

            // Inserción masiva de nuevos deals
            if (!empty($newDeals)) {
                Negocio::insert($newDeals);
            }

            // Actualización masiva de deals existentes
            if (!empty($dealsToUpdate)) {
                foreach ($dealsToUpdate as $dealUpdate) {
                    Negocio::where('id', $dealUpdate['id'])->update($dealUpdate['data']);
                }
            }

            $negocios = Negocio::where("user_id", $user->id)->get();

        } catch (\Exception $e) {
            \Log::error("Error en el proceso principal: " . $e->getMessage());
            throw $e;
        }

        $processedUrls = $this->processFilesConcurrently($urls, $user, $this->hubspotService);
        $processedContactFiles = $this->processFilesConcurrently($HScontactFiles, $user, $this->hubspotService);

        // Obtener archivos del cliente
        $archivos = File::where("IDCliente", $user->passport)->get();

        // Mapear campos de HubSpot con los de la base de datos
        $hubspotFields = [
            'fecha_nac' => 'date_of_birth',
            'firstname' => 'nombres',
            'lastmodifieddate' => 'updated_at',
            'lastname' => 'apellidos',
            'n000__referido_por__clonado_' => 'referido_por',
            'numero_de_pasaporte' => 'passport',
            'servicio_solicitado' => 'servicio',
        ];

        // Recorrer propiedades de HubSpot y añadir las faltantes al arreglo
        foreach ($HScontact['properties'] as $hsField => $value) {
            if (!array_key_exists($hsField, $hubspotFields) && $hsField != "createdate" && $hsField != "hs_object_id") {
                // Agrega automáticamente un nuevo campo con una clave genérica
                $hubspotFields[$hsField] = $hsField; // Usa el mismo nombre como clave en DB
            }
        }

        $updatesToDB = [];
        $updatesToHubSpot = [];

        $hsLastModified = new \DateTime($HScontact['properties']['lastmodifieddate']);
        $dbLastModified = new \DateTime($user->updated_at);

        $utcTimezone = new \DateTimeZone('UTC');
        $hsLastModified->setTimezone($utcTimezone);
        $dbLastModified->setTimezone($utcTimezone);

        foreach ($hubspotFields as $hsField => $dbField) {
            $hubspotValue = $HScontact['properties'][$hsField] ?? null;
            $dbValue = $user->{$dbField};

            if ($hsField != 'lastmodifieddate') {
                if ($hubspotValue !== $dbValue) {
                    if ($hubspotValue && (!$dbValue || $hsLastModified > $dbLastModified)) {
                        if ($hsField != "updated_at") {

                            // 🚨 PROTECCIÓN PARA FECHA NACIMIENTO
                            if (in_array($hsField, ['fecha_nac', 'date_of_birth'])) {
                                if (!empty($dbValue) && $dbValue != '0000-00-00') {
                                    // Si ya tienes una fecha válida, NO sobreescribas
                                    continue;
                                } else {
                                    // Si no tienes fecha, convierte EPOCH a fecha normal antes de guardar
                                    if (is_numeric($hubspotValue)) {
                                        $hubspotDate = (new \DateTime())->setTimestamp($hubspotValue / 1000);
                                        $hubspotValue = $hubspotDate->format('Y-m-d');
                                    }
                                }
                            }

                            $user->{$dbField} = $hubspotValue;
                            $updatesToDB[$dbField] = $hubspotValue;
                        }
                    } else if ($dbValue && (!$hubspotValue || $dbLastModified > $hsLastModified)) {
                        switch ($hsField) {
                            case 'fecha_nac':
                            case 'date_of_birth':
                                if (!empty($dbValue) && $dbValue != "0000-00-00") {
                                    try {
                                        // Convertir fecha DB a EPOCH para Hubspot
                                        $onlyDate = (new \DateTime($dbValue))->format('Y-m-d');
                                        $dbDate = new \DateTime($onlyDate, new \DateTimeZone('UTC'));
                                        $dbTimestampMs = $dbDate->getTimestamp() * 1000;

                                        $hubspotTimestampMs = null;
                                        if ($hubspotValue !== null && is_numeric($hubspotValue)) {
                                            $hubspotDate = (new \DateTime())->setTimestamp($hubspotValue / 1000);
                                            $hubspotDate->setTimezone(new \DateTimeZone('UTC'));
                                            $hubspotTimestampMs = $hubspotDate->getTimestamp() * 1000;
                                        }

                                        if ($hubspotTimestampMs !== $dbTimestampMs) {
                                            $updatesToHubSpot[$hsField] = $dbTimestampMs;
                                        }
                                    } catch (\Exception $e) {
                                        // Manejar error si fecha inválida
                                    }
                                }
                                break;

                            case 'genero':
                                $cleanValue = trim($dbValue);
                                $mapping = [
                                    'MASCULINO' => 'MASCULINO / MALE',
                                    'FEMENINO'  => 'FEMENINO / FEMALE',
                                    'OTROS'     => 'OTROS / OTHERS',
                                ];
                                if (isset($mapping[$cleanValue])) {
                                    $mappedValue = $mapping[$cleanValue];
                                    if ($hubspotValue !== $mappedValue) {
                                        $updatesToHubSpot[$hsField] = $mappedValue;
                                    }
                                }
                                break;

                            default:
                                if ($hsField == "cantidad_alzada") {
                                    if (strval($hubspotValue) !== strval($dbValue)) {
                                        $updatesToHubSpot[$hsField] = $dbValue;
                                    }
                                } else {
                                    if ($hubspotValue !== $dbValue) {
                                        $updatesToHubSpot[$hsField] = $dbValue;
                                    }
                                }
                                break;
                        }
                    }
                }
            }
        }


        $excludedKeys = ['lastmodifieddate', 'referido_por']; // Lista de claves a excluir
        $updatesToHubSpot = array_filter(
            $updatesToHubSpot,
            fn($key) => !in_array($key, $excludedKeys),
            ARRAY_FILTER_USE_KEY
        );

        if (isset($updatesToDB['date_of_birth']) && is_numeric($updatesToDB['date_of_birth'])) {
            if ((int)$updatesToDB['date_of_birth']) {
                unset($updatesToDB['date_of_birth']); // Elimina el campo si es EPOCH
            }
        }

        // Guardar los cambios en la base de datos si hubo actualizaciones
        if (!empty($updatesToDB)) {
            $user->save();
        }

        // Actualizar HubSpot si hubo cambios
        if (!empty($updatesToHubSpot)) {
            try {
                // Aquí llamas a tu servicio HubSpot que hace el PATCH
                $this->hubspotService->updateContact($user->hs_id, $updatesToHubSpot);
            } catch (ClientException $e) {
                // Obtén la respuesta completa en formato string
                $responseBodyAsString = (string) $e->getResponse()->getBody();
            }
        }

        // Obtener tratos asociados desde HubSpot
        $HSdeals = $this->hubspotService->getDealsByContactId($user->hs_id);

        // Monday
        if (!$user->monday_id) {
            $mondayUserDetailsPre = $this->searchUserInMonday($user->passport, $user);
        }

        $query = "
            items(ids: [{$user->monday_id}]) {
                id
                name
                board {
                    id
                    name
                }
                column_values {
                    id
                    column {
                        title
                        type
                    }
                    text
                    value
                }
            }
        ";

        $result = json_decode(json_encode(Monday::customQuery($query)), true);

        if (isset($result['items'][0]['board']['name'])){
            $mondaydataforAI = [
                "tablero" => $result['items'][0]['board']['name']
            ];
        } else {
            $mondaydataforAI = [];
        }

        // Registrar hora de fin

        $mondayUserDetailsPre = $result['items'][0] ?? null;

        // Guardar datos del usuario en Monday
        if ($mondayUserDetailsPre) {
            $this->storeMondayUserData($user, $mondayUserDetailsPre);
            $boardId = $mondayUserDetailsPre['board']['id'] ?? null;
            $boardName = $mondayUserDetailsPre['board']['name'] ?? null;

            // Guardar las columnas del board en `monday_form_builder`
            if ($boardId) {
                $this->storeMondayBoardColumns($boardId);
            }

            $mondayData = json_decode(MondayData::where('user_id', $user->id)->first(), true);
            $mondayData["data"] = json_decode($mondayData["data"] , true);

            $dataMonday = [];

            foreach($mondayData["data"]["column_values"] as $key => $campo){
                $dataMonday[$campo["id"]] = $campo["text"];
            }

            $mondayFormBuilder = json_decode(MondayFormBuilder::where('board_id', $boardId)->get(), true);

            foreach($mondayFormBuilder as $key=>$campo){
                $mondayFormBuilder[$key]["settings"] = json_decode($campo["settings"], true);
            }

            $mondayUserDetails = [];
            $mondayUserDetails["nombre"] = $mondayUserDetailsPre["name"];
            $mondayUserDetails["id"] = $mondayUserDetailsPre["id"];

            foreach($mondayUserDetailsPre["column_values"] as $key=>$element){
                $mondayUserDetails["propiedades"][$element["id"]] = [$element["column"]["title"], $element["text"]];
            }
        } else {
            $dataMonday = [];
            $mondayData = [];
            $mondayFormBuilder = [];
            $mondayUserDetails = [];
            $boardId = 0;
            $boardName = "";
        }



        // Preparar datos para la vista
        $roles = Role::all();
        $permissions = Permission::all();
        $servicios = Servicio::all();

        // Obtener datos de la base de datos de forma más eficiente
        $people = Agcliente::where("IDCliente", trim($user->passport))->get()->toArray();

        // Optimización 1: Crear un mapa rápido de búsqueda por ID
        $peopleMap = [];
        foreach ($people as $person) {
            $peopleMap[$person['id']] = $person;
        }

        // Optimización 2: Calcular generaciones usando un enfoque iterativo mejorado
        $generaciones = [];

        // Primera pasada: identificar raíces (personas sin padres)
        $queue = [];
        foreach ($people as $person) {
            if ($person['idPadreNew'] === null && $person['idMadreNew'] === null) {
                $generaciones[$person['id']] = 1;
                $queue[] = $person['id'];
            }
        }

        // Procesamiento por niveles (BFS usando array en lugar de SplQueue)
        while (!empty($queue)) {
            $currentId = array_shift($queue);
            $currentGeneration = $generaciones[$currentId];

            // Buscar hijos de esta persona
            foreach ($people as $person) {
                if ($person['idPadreNew'] == $currentId || $person['idMadreNew'] == $currentId) {
                    if (!isset($generaciones[$person['id']]) || $generaciones[$person['id']] < $currentGeneration + 1) {
                        $generaciones[$person['id']] = $currentGeneration + 1;
                        $queue[] = $person['id'];
                    }
                }
            }
        }

        $maxGeneraciones = empty($generaciones) ? 1 : max($generaciones) + 1;

        // Optimización 3: Construir la estructura de columnas
        $columnasparatabla = [];

        for ($i = 0; $i < $maxGeneraciones; $i++) {
            $columnasparatabla[$i] = [];

            if ($i == 0) {
                // Caso especial para el cliente (primera generación)
                if (!empty($people)) {
                    $people[0]['showbtn'] = 2;
                    $columnasparatabla[$i][] = $people[0];
                }
                continue;
            }

            // Procesar generación anterior para construir la actual
            foreach ($columnasparatabla[$i-1] as $personaAnterior) {
                // Procesar padre
                $this->processParent($columnasparatabla[$i], $personaAnterior, 'idPadreNew', 'm', $peopleMap);

                // Procesar madre
                $this->processParent($columnasparatabla[$i], $personaAnterior, 'idMadreNew', 'f', $peopleMap);
            }
        }

        // Optimización 4: Precalcular parentescos
        $parentescos = [
            0 => ["Cliente"],
            1 => ["Padre", "Madre"]
        ];

        $parentescos_post_padres = [
            "Abuel",
            "Bisabuel",
            "Tatarabuel",
            "Trastatarabuel",
            "Retatarabuel",
            "Sestarabuel",
            "Setatarabuel",
            "Octatarabuel",
            "Nonatarabuel",
            "Decatarabuel",
            "Undecatarabuel",
            "Duodecatarabuel",
            "Trececatarabuel",
            "Catorcatarabuel",
            "Quincecatarabuel",
            "Deciseiscatarabuel",
            "Decisietecatarabuel",
            "Deciochocatarabuel",
            "Decinuevecatarabuel",
            "Vigecatarabuel",
            "Vigecimoprimocatarabuel",
            "Vigecimosegundocatarabuel",
            "Vigecimotercercatarabuel",
            "Vigecimocuartocatarabuel",
            "Vigecimoquintocatarabuel",
            "Vigecimosextocatarabuel",
            "Vigecimoseptimocatarabuel",
            "Vigecimooctavocatarabuel",
            "Vigecimonovenocatarabuel",
            "Trigecatarabuel",
            "Trigecimoprimocatarabuel",
            "Trigecimosegundocatarabuel",
            "Trigecimotercercatarabuel",
            "Trigecimocuartocatarabuel",
            "Trigecimoquintocatarabuel",
            "Trigecimosextocatarabuel",
            "Trigecimoseptimocatarabuel",
            "Trigecimooctavocatarabuel",
            "Trigecimonovenocatarabuel",
            "Cuarentacatarabuel",
            "Cuarentaprimocatarabuel",
            "Cuarentasegundocatarabuel",
            "Cuarentatercercatarabuel",
        ];

        $prepar = 4;
        foreach ($parentescos_post_padres as $key => $parentesco) {
            if ($key + 2 < $maxGeneraciones) {
                $parentescos[$key + 2] = [];
                for ($i = 0; $i < $prepar; $i++) {
                    $suffix = ($i % 2 == 0 ? "o" : "a");
                    $text = $this->generateRelationshipText($i, $key);
                    $parentescos[$key + 2][] = $parentesco . $suffix . " " . $text;
                }
                $prepar *= 2;
            }
        }

        // Asignar parentescos
        foreach ($columnasparatabla as $key => $generacion) {
            foreach ($generacion as $idx => $persona) {
                $columnasparatabla[$key][$idx]['parentesco'] = $parentescos[$key][$idx] ?? 'Desconocido';
            }
        }

        $hayTatarabuelo = isset($columnasparatabla[4]) && count($columnasparatabla[4]) > 0;

        // aqui arranca el COS

        /* UPDATE 06/16/2025

        En esta versión, el proceso va al revés... es decir: de FIN, desde la fase final (cuando le aprueban la nacionalidad al cliente)
        hasta el registro del cliente (no es mala idea, si me lo preguntas... porque esto me permite revisar muchas cosas que, convenientemente,
        son importantes en el proceso, y voy dandole mas prioridad a lo que va de último)... así, resuelvo un peo gigante.*/

        //Esta variable sirve para obtener el servicio contratado por el usuario...
        //pero tomaremos en consideración el del negocio, en caso de que tenga negocios.
        $servicename = Servicio::where("id_hubspot", "like", $user->servicio."%")->first();

        $cosuser = [];

        $hoy = Carbon::now();

        if (isset($dataMonday["men__desplegable"])){
            $mondaydataforAI["etiquetas"] = $dataMonday["men__desplegable"];
            $mondaydataforAI["información_genealogia"] = $this->obtenerValorPorTitulo($result, 'INFO GENEALOGIA');
            $mondaydataforAI["información_ventas"] = $this->obtenerValorPorTitulo($result, 'INFO VENTAS/ATC');
            $mondaydataforAI["solicitud_cliente"] = $this->obtenerValorPorTitulo($result, 'CLIENTE SOLICITUD');
            $mondaydataforAI["respuesta_solicitud"] = $this->obtenerValorPorTitulo($result, 'Estado Solicitud CDD');
            $mondaydataforAI["arbol_cargado"] = $this->obtenerValorPorTitulo($result, 'ARBOL CARGADO');
            $mondaydataforAI["inicio_investigacion"] = $this->obtenerValorPorTitulo($result, 'ARBOL CARGADO');
        }

        $cos2      = $user->arraycos;          // datos ya calculados
        $expires  = $user->arraycos_expire;

        if(count($negocios)>0) {
            foreach($negocios as $negocio) {
                $certificadoDescargado = 0;

                if (
                    isset($negocio->fase_2_pagado) || isset($negocio->fase_2_pagado__teamleader_) ||
                    isset($negocio->fase_3_pagado) || isset($negocio->fase_3_pagado__teamleader_) ||
                    isset($negocio->n5__fecha_de_formalizacion)
                ) {
                    $resultadoIA = [
                        "otrosProcesos" => false,
                        "pericial" => false,
                        "genealogiaAprobada" => false,
                        "genealogia" => false,
                        "investigacionProfunda" => false,
                        "investigacionInSitu" => false,
                        "analisisYCorreccion" => false,
                        "investigacionIntuituPersonae" => false
                    ];
                } else {
                    $resultadoIA = $this->analizarEtiquetasYDevolverJSON($mondaydataforAI);
                }
                if (isset($negocio->n5__fecha_de_formalizacion) || ( isset($negocio->codigo_de_proceso) && $negocio->codigo_de_proceso == "FORMALIZADO 2024" )) {
                    if(!isset($negocio->n4__certificado_descargado)){
                        $certificadoDescargado = 2;
                    }
                    if (isset($negocio->nacionalidad_concedida) || isset($negocio->n7__fecha_de_resolucion)){
                        $cosuser[] = [
                            "servicio" => $negocio->servicio_solicitado,
                            "warning" => null,
                            "certificadoDescargado" => $certificadoDescargado,
                            "currentStepGen" => 18 - $certificadoDescargado,
                            "currentStepJur" => 8
                        ];
                        continue;
                    }

                    $tieneViajudicialActivo = $this->verificarNegocioActivo(
                            $negocios,
                            'Demanda Judicial',
                            ['Demanda', 'Judicial']
                        );

                    if ($tieneViajudicialActivo) {
                        $cosuser[] = [
                            "servicio" => $negocio->servicio_solicitado,
                            "warning" => null,
                            "certificadoDescargado" => $certificadoDescargado,
                            "currentStepGen" => 18 - $certificadoDescargado,
                            "currentStepJur" => 7
                        ];

                        continue;
                    }

                    if (isset($negocio->n13__fecha_recurso_alzada)){
                        $fechaRecurso = Carbon::parse($negocio->n13__fecha_recurso_alzada);
                        $fechaRecursoMas3Meses = $fechaRecurso->copy()->addMonths(3);
                        if ($fechaRecursoMas3Meses->greaterThan($hoy)){
                            if ($fechaRecursoMas3Meses->greaterThan($hoy)) {

                                $tieneViajudicialActivo = $this->verificarNegocioActivo(
                                    $negocios,
                                    'Demanda Judicial',
                                    ['Demanda', 'Judicial']
                                );

                                $warning = ($tieneViajudicialActivo || isset($negocio->fecha_solicitud_viajudicial))
                                    ? null
                                    : "<b>¡Puedes solicitar la vía judicial!</b>";

                                $cosuser[] = [
                                    "servicio" => $negocio->servicio_solicitado,
                                    "warning" => $warning,
                                    "certificadoDescargado" => $certificadoDescargado,
                                    "currentStepGen" => 18 - $certificadoDescargado,
                                    "currentStepJur" => 7
                                ];
                                continue;
                            }
                        }
                    }

                    // ✅ MODIFICACIÓN: Si es FORMALIZADO 2024, usar 01-01-2024 como fecha base
                    if (isset($negocio->codigo_de_proceso) && $negocio->codigo_de_proceso == "FORMALIZADO 2024") {
                        $fechaFormalizacion = Carbon::parse('2024-01-01');
                    } else {
                        $fechaFormalizacion = Carbon::parse($negocio->n5__fecha_de_formalizacion);
                    }

                    $fechaFormalizacionMas12Meses = $fechaFormalizacion->copy()->addMonths(12);
                    $fechaFormalizacionMas6Meses = $fechaFormalizacion->copy()->addMonths(6);
                    $fechaFormalizacionMas1Meses = $fechaFormalizacion->copy()->addMonths(1);
                    if ($hoy->greaterThan($fechaFormalizacionMas12Meses)){
                        if ($hoy->greaterThan($fechaFormalizacionMas12Meses)) {
                            $tieneRecursoAlzadaActivo = $this->verificarNegocioActivo(
                                $negocios,
                                'Recurso de Alzada',
                                ['Recurso', 'Alzada']
                            );

                            $warning = ($tieneRecursoAlzadaActivo || isset($negocio->fecha_solicitud_recursoalzada))
                                ? null
                                : '<b>¡Solicita tu Recurso de Alzada!</b><a style="border:0!important;" href="https://sefaruniversal.com/landing-email-de-recurso-de-alzada/" class="cfrSefar inline-flex items-center justify-center px-3 py-1 ml-2 text-decoration-none text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Solicita el Recurso de Alzada</a>';

                            $cosuser[] = [
                                "servicio" => $negocio->servicio_solicitado,
                                "warning" => $warning,
                                "certificadoDescargado" => $certificadoDescargado,
                                "currentStepGen" => 18 - $certificadoDescargado,
                                "currentStepJur" => 6
                            ];
                            continue;
                        }
                    } else if ($hoy->greaterThan($fechaFormalizacionMas6Meses)){
                        if ($hoy->greaterThan($fechaFormalizacionMas6Meses)) {
                            $tieneResolucionExpresaActivo = $this->verificarNegocioActivo(
                                $negocios,
                                'SOLICITUD DE DOCUMENTO DE RESOLUCIÓN EXPRESA',
                                ['Resolución', 'Expresa']
                            );

                            $warning = ($tieneResolucionExpresaActivo || isset($negocio->fecha_solicitud_resolucionexpresa))
                                ? null
                                : '<b>¡Solicita tu resolución expresa!</b><a href="https://sefaruniversal.com/resolucion-expresa/" style="border:0!important;" class="cfrSefar inline-flex items-center justify-center px-3 py-1 ml-2 text-decoration-none text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Solicita tu Resolución Expresa</a>';

                            $cosuser[] = [
                                "servicio" => $negocio->servicio_solicitado,
                                "warning" => $warning,
                                "certificadoDescargado" => $certificadoDescargado,
                                "currentStepGen" => 18 - $certificadoDescargado,
                                "currentStepJur" => 5
                            ];
                            continue;
                        }
                    } else if ($hoy->greaterThan($fechaFormalizacionMas1Meses)){
                        // ✅ Verificar si tiene negocio de Subsanación activo
                        $tieneSubsanacionActivo = $this->verificarNegocioActivo(
                            $negocios,
                            'Subsanación de Expediente',
                            ['Subsanación']
                        );

                        $warning = $tieneSubsanacionActivo
                            ? null
                            : '<b>¡Consulta si requieres subsanación o mejora de expediente!</b><a style="border:0!important;" href="https://sefaruniversal.com/landing-registro-subsanacion-de-la-nacionalidad-espanola-sefardi/" class="cfrSefar inline-flex items-center justify-center px-3 py-1 ml-2 text-decoration-none text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">¡Consulta ahora!</a>';

                        $cosuser[] = [
                            "servicio" => $negocio->servicio_solicitado,
                            "certificadoDescargado" => $certificadoDescargado,
                            "warning" => $warning,
                            "currentStepGen" => 18 - $certificadoDescargado,
                            "currentStepJur" => 4
                        ];
                        continue;
                    }

                    $cosuser[] = [
                        "servicio" => $negocio->servicio_solicitado,
                        "warning" => null,
                        "certificadoDescargado" => $certificadoDescargado,
                        "currentStepGen" => 18 - $certificadoDescargado,
                        "currentStepJur" => 3
                    ];
                    continue;

                } else if(isset($negocio->fase_3_pagado) || isset($negocio->fase_3_pagado__teamleader_)) {
                    if(!isset($negocio->n4__certificado_descargado)){
                        $certificadoDescargado = 2;
                    }
                    if (isset($negocio->nacionalidad_concedida) || isset($negocio->n7__fecha_de_resolucion)){
                        $cosuser[] = [
                            "servicio" => $negocio->servicio_solicitado2,
                            "warning" => null,
                            "certificadoDescargado" => $certificadoDescargado,
                            "currentStepGen" => 18 - $certificadoDescargado,
                            "currentStepJur" => 8
                        ];
                        continue;
                    }

                    if (isset($negocio->n13__fecha_recurso_alzada)){
                        $fechaRecurso = Carbon::parse($negocio->n13__fecha_recurso_alzada);
                        $fechaRecursoMas3Meses = $fechaRecurso->copy()->addMonths(3);
                        if ($fechaRecursoMas3Meses->greaterThan($hoy)){
                            if ($fechaRecursoMas3Meses->greaterThan($hoy)) {
                                $tieneViajudicialActivo = $this->verificarNegocioActivo(
                                    $negocios,
                                    'Demanda Judicial',
                                    ['Demanda', 'Judicial']
                                );

                                $warning = ($tieneViajudicialActivo || isset($negocio->fecha_solicitud_viajudicial))
                                    ? null
                                    : "<b>¡Puedes solicitar la vía judicial!</b>";

                                $cosuser[] = [
                                    "servicio" => $negocio->servicio_solicitado,
                                    "warning" => $warning,
                                    "certificadoDescargado" => $certificadoDescargado,
                                    "currentStepGen" => 18 - $certificadoDescargado,
                                    "currentStepJur" => 7
                                ];
                                continue;
                            }
                        }
                    }

                    if (isset($negocio->n5__fecha_de_formalizacion)){
                        $fechaFormalizacion = Carbon::parse($negocio->n5__fecha_de_formalizacion);

                        $fechaFormalizacionMas12Meses = $fechaFormalizacion->copy()->addMonths(12);
                        $fechaFormalizacionMas6Meses = $fechaFormalizacion->copy()->addMonths(6);
                        $fechaFormalizacionMas1Meses = $fechaFormalizacion->copy()->addMonths(1);
                        if ($hoy->greaterThan($fechaFormalizacionMas12Meses)){
                            if ($hoy->greaterThan($fechaFormalizacionMas12Meses)) {
                               $tieneRecursoAlzadaActivo = $this->verificarNegocioActivo(
                                    $negocios,
                                    'Recurso de Alzada',
                                    ['Recurso', 'Alzada']
                                );

                                $warning = ($tieneRecursoAlzadaActivo || isset($negocio->fecha_solicitud_recursoalzada))
                                    ? null
                                    : '<b>¡Solicita tu Recurso de Alzada!</b><a style="border:0!important;" href="https://sefaruniversal.com/landing-email-de-recurso-de-alzada/" class="cfrSefar inline-flex items-center justify-center px-3 py-1 ml-2 text-decoration-none text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Solicita el Recurso de Alzada</a>';

                                $cosuser[] = [
                                    "servicio" => $negocio->servicio_solicitado,
                                    "warning" => $warning,
                                    "certificadoDescargado" => $certificadoDescargado,
                                    "currentStepGen" => 18 - $certificadoDescargado,
                                    "currentStepJur" => 6
                                ];
                                continue;
                            }
                        } else if ($hoy->greaterThan($fechaFormalizacionMas6Meses)){
                            if ($hoy->greaterThan($fechaFormalizacionMas6Meses)) {
                                $tieneResolucionExpresaActivo = $this->verificarNegocioActivo(
                                    $negocios,
                                    'SOLICITUD DE DOCUMENTO DE RESOLUCIÓN EXPRESA',
                                    ['Resolución', 'Expresa']
                                );

                                $warning = ($tieneResolucionExpresaActivo || isset($negocio->fecha_solicitud_resolucionexpresa))
                                    ? null
                                    : '<b>¡Solicita tu resolución expresa!</b><a href="https://sefaruniversal.com/resolucion-expresa/" style="border:0!important;" class="cfrSefar inline-flex items-center justify-center px-3 py-1 ml-2 text-decoration-none text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Solicita tu Resolución Expresa</a>';

                                $cosuser[] = [
                                    "servicio" => $negocio->servicio_solicitado,
                                    "warning" => $warning,
                                    "certificadoDescargado" => $certificadoDescargado,
                                    "currentStepGen" => 18 - $certificadoDescargado,
                                    "currentStepJur" => 5
                                ];
                                continue;
                            }
                        } else if ($hoy->greaterThan($fechaFormalizacionMas1Meses)){
                            $tieneSubsanacionActivo = $this->verificarNegocioActivo(
                                $negocios,
                                'Subsanación de Expediente',
                                ['Subsanación']
                            );

                            $warning = $tieneSubsanacionActivo
                                ? null
                                : '<b>¡Consulta si requieres subsanación o mejora de expediente!</b><a style="border:0!important;" href="https://sefaruniversal.com/landing-registro-subsanacion-de-la-nacionalidad-espanola-sefardi/" class="cfrSefar inline-flex items-center justify-center px-3 py-1 ml-2 text-decoration-none text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">¡Consulta ahora!</a>';

                            $cosuser[] = [
                                "servicio" => $negocio->servicio_solicitado,
                                "certificadoDescargado" => $certificadoDescargado,
                                "warning" => $warning,
                                "currentStepGen" => 18 - $certificadoDescargado,
                                "currentStepJur" => 4
                            ];
                            continue;
                        } else {
                            $cosuser[] = [
                                "servicio" => $negocio->servicio_solicitado2,
                                "warning" => null,
                                "certificadoDescargado" => $certificadoDescargado,
                                "currentStepGen" => 18 - $certificadoDescargado,
                                "currentStepJur" => 3
                            ];
                            continue;
                        }
                    }

                    if (isset($negocio->tasa_pagada)){
                        $cosuser[] = [
                            "servicio" => $negocio->servicio_solicitado2,
                            "warning" => null,
                            "certificadoDescargado" => $certificadoDescargado,
                            "currentStepGen" => 18 - $certificadoDescargado,
                            "currentStepJur" => 2
                        ];
                        continue;
                    }

                    if (isset($negocio->enviado_a_pago_de_tasas)){
                        $cosuser[] = [
                            "servicio" => $negocio->servicio_solicitado2,
                            "warning" => null,
                            "certificadoDescargado" => $certificadoDescargado,
                            "currentStepGen" => 18 - $certificadoDescargado,
                            "currentStepJur" => 1
                        ];
                        continue;
                    }

                    $cosuser[] = [
                        "servicio" => $negocio->servicio_solicitado2,
                        "warning" => null,
                        "certificadoDescargado" => $certificadoDescargado,
                        "currentStepGen" => 18 - $certificadoDescargado,
                        "currentStepJur" => 0
                    ];
                    continue;

                } else if ( isset($negocio->fase_2_pagado) || isset($negocio->fase_2_pagado__teamleader_) ) {
                    if (isset($negocio->fase_3_preestab) || isset($negocio->fase_3_preestab) || isset($negocio->fase_3_preestab)) {
                        if(!isset($negocio->n4__certificado_descargado)){
                            $certificadoDescargado = 2;
                        }
                        $cosuser[] = [
                            "servicio" => $negocio->servicio_solicitado2,
                            "certificadoDescargado" => $certificadoDescargado,
                            "warning" => "<b>Realiza el pago para la formalización del expediente</b> y aseguremos juntos el siguiente gran paso hacia tu ciudadanía española.",
                            "currentStepGen" => 18 - $certificadoDescargado,
                            "currentStepJur" => -1
                        ];
                        continue;
                    }

                    if(isset($negocio->n4__certificado_descargado)){
                        $cosuser[] = [
                            "servicio" => $negocio->servicio_solicitado2,
                            "warning" => null,
                            "certificadoDescargado" => $certificadoDescargado,
                            "currentStepGen" => 18,
                            "currentStepJur" => -1
                        ];
                        continue;
                    }

                    if (isset($negocio->n3__informe_cargado)){
                        $fechaInformeCargado = Carbon::parse($negocio->n3__informe_cargado);
                        $fechaInformeCargadoMas1Meses = $fechaInformeCargado->copy()->addMonths(1);

                        if ($fechaInformeCargadoMas1Meses->greaterThan($hoy)) {
                            $cosuser[] = [
                                "servicio" => $negocio->servicio_solicitado2,
                                "warning" => null,
                                "certificadoDescargado" => $certificadoDescargado,
                                "currentStepGen" => 17,
                                "currentStepJur" => -1
                            ];
                            continue;
                        } else {
                            $cosuser[] = [
                                "servicio" => $negocio->servicio_solicitado2,
                                "warning" => null,
                                "certificadoDescargado" => $certificadoDescargado,
                                "currentStepGen" => 16,
                                "currentStepJur" => -1
                            ];
                            continue;
                        }
                    }

                    $cosuser[] = [
                        "servicio" => $negocio->servicio_solicitado2,
                        "warning" => null,
                        "certificadoDescargado" => $certificadoDescargado,
                        "currentStepGen" => 15,
                        "currentStepJur" => -1
                    ];
                    continue;
                } else if ( isset($negocio->fase_1_pagado) || isset($negocio->fase_1_pagado__teamleader_) ) {
                    if (isset($negocio->fase_2_preestab) || isset($negocio->fase_2_preestab) || isset($negocio->fase_2_preestab)){
                        $cosuser[] = [
                            "servicio" => $negocio->servicio_solicitado2,
                            "certificadoDescargado" => $certificadoDescargado,
                            "warning" => "Para continuar con el proceso y proceder con el envío del informe y las pruebas correspondientes a la institución mencionada, <b>es necesario que realices el siguiente pago.</b>",
                            "currentStepGen" => 15,
                            "currentStepJur" => -1
                        ];
                        continue;
                    }

                    if ($resultadoIA['otrosProcesos']) {
                        $cosuser[] = [
                            "servicio" => $negocio->servicio_solicitado2,
                            "certificadoDescargado" => $certificadoDescargado,
                            "warning" => "<b>Tu caso ha sido derivado a otro proceso.</b> Recibirás seguimiento personalizado.",
                            "currentStepGen" => 11,
                            "currentStepJur" => -1
                        ];
                        continue;
                    }

                    $documentsTOTAL = DocumentRequest::where('user_id', $user->id)
                                    ->count();

                    if($documentsTOTAL>0) {
                        $documentsEnEsperaCliente = DocumentRequest::where('user_id', $user->id)
                                        ->whereIn('status', ['en_espera_cliente', 'rechazada'])
                                        ->count();

                        $documentsEnRevision = DocumentRequest::where('user_id', $user->id)
                                        ->where('status', 'resuelto')
                                        ->count();

                        $documentsAprobados = DocumentRequest::where('user_id', $user->id)
                                        ->whereIn('status', ['no_documento', 'aprobada'])
                                        ->count();

                        if ($documentsAprobados>0) {
                            $cosuser[] = [
                                "servicio" => $negocio->servicio_solicitado2,
                                "warning" => null,
                                "certificadoDescargado" => $certificadoDescargado,
                                "currentStepGen" => 10,
                                "currentStepJur" => -1
                            ];
                            continue;
                        }

                        if ($documentsEnRevision>0) {
                            $cosuser[] = [
                                "servicio" => $negocio->servicio_solicitado2,
                                "warning" => null,
                                "certificadoDescargado" => $certificadoDescargado,
                                "currentStepGen" => 9,
                                "currentStepJur" => -1
                            ];
                            continue;
                        }

                        if ($documentsEnEsperaCliente>0) {
                            $cosuser[] = [
                                "servicio" => $negocio->servicio_solicitado2,
                                "certificadoDescargado" => $certificadoDescargado,
                                "warning" => "Tienes solicitudes de documentos pendientes. Para resolverlas, dirígete a la pestaña de 'Mis solicitudes de documentos'",
                                "currentStepGen" => 8,
                                "currentStepJur" => -1
                            ];
                            continue;
                        }
                    }

                    $cosuser[] = [
                        "servicio" => $negocio->servicio_solicitado2,
                        "warning" => null,
                        "certificadoDescargado" => $certificadoDescargado,
                        "currentStepGen" => 8,
                        "currentStepJur" => -1
                    ];
                    continue;

                } else {
                    if (isset($negocio->fase_1_preestab) || isset($negocio->fase_1_preestab) || isset($negocio->fase_1_preestab)){
                        $cosuser[] = [
                            "servicio" => $negocio->servicio_solicitado2,
                            "certificadoDescargado" => $certificadoDescargado,
                            "warning" => "Para continuar con el proceso y proceder con la redacción del informe, <b>es necesario que realices el siguiente pago.</b>",
                            "currentStepGen" => 7,
                            "currentStepJur" => -1
                        ];
                        continue;
                    }

                    if ($resultadoIA['genealogia']) {
                        $cosuser[] = [
                            "servicio" => $negocio->servicio_solicitado2,
                            "warning" => null,
                            "certificadoDescargado" => $certificadoDescargado,
                            "currentStepGen" => 5,
                            "currentStepJur" => -1
                        ];
                        continue;
                    }

                    if ($resultadoIA['inicioInvestigacion']) {
                        $cosuser[] = [
                            "servicio" => $negocio->servicio_solicitado2,
                            "warning" => null,
                            "certificadoDescargado" => $certificadoDescargado,
                            "currentStepGen" => 4,
                            "currentStepJur" => -1
                        ];
                        continue;
                    }

                    if ($resultadoIA['investigacionProfunda']) {
                        $cosuser[] = [
                            "servicio" => $negocio->servicio_solicitado2,
                            "warning" => null,
                            "certificadoDescargado" => $certificadoDescargado,
                            "currentStepGen" => 3,
                            "currentStepJur" => -1
                        ];
                        continue;
                    }

                    if ($resultadoIA['investigacionInSitu']) {
                        $cosuser[] = [
                            "servicio" => $negocio->servicio_solicitado2,
                            "warning" => null,
                            "certificadoDescargado" => $certificadoDescargado,
                            "currentStepGen" => 2,
                            "currentStepJur" => -1
                        ];
                        continue;
                    }

                    if ($resultadoIA['investigacionIntuituPersonae']) {
                        $cosuser[] = [
                            "servicio" => $negocio->servicio_solicitado2,
                            "warning" => null,
                            "certificadoDescargado" => $certificadoDescargado,
                            "currentStepGen" => 1,
                            "currentStepJur" => -1,
                            "subproceso" => 1
                        ];
                        continue;
                    }

                    if ($resultadoIA['analisisYCorreccion'] || $hayTatarabuelo) {
                        $cosuser[] = [
                            "servicio" => $negocio->servicio_solicitado2,
                            "warning" => null,
                            "certificadoDescargado" => $certificadoDescargado,
                            "currentStepGen" => 1,
                            "currentStepJur" => -1,
                            "subproceso" => 0,
                        ];
                        continue;
                    }

                    $cosuser[] = [
                        "servicio" => $negocio->servicio_solicitado2,
                        "warning" => null,
                        "certificadoDescargado" => $certificadoDescargado,
                        "currentStepGen" => 1,
                        "currentStepJur" => -1,
                        "subproceso" => -1,
                    ];
                    continue;
                }
            }
        } else {
            $cosuser[0]["servicio"] = $servicename["id_hubspot"] ?? "";
            if ($user->pay == 0) {
                $cosuser[0]["warning"] = "Debes realizar el pago del registro de tu proceso.<a style='border:0!important;'  href='/pay' class='cfrSefar inline-flex items-center justify-center px-3 py-1 ml-2 text-decoration-none   text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700'>Pagar registro</a>";
                $cosuser[0]["currentStepGen"] = 0;
                $cosuser[0]["currentStepJur"] = -1;
            } else if ($user->pay == 1){
                $cosuser[0]["warning"] = "Debes completar tu información para continuar con el proceso.<a style='border:0!important;'  href='/getinfo' class='cfrSefar inline-flex items-center justify-center px-3 py-1 ml-2 text-decoration-none   text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700'>Completar información</a>";
                $cosuser[0]["currentStepGen"] = 0;
                $cosuser[0]["currentStepJur"] = -1;
            } else if ($user->contrato == 0){
                $cosuser[0]["warning"] = "Debes firmar tu contrato para continuar con el proceso.<a style='border:0!important;'  href='/contrato' class='cfrSefar inline-flex items-center justify-center px-3 py-1 ml-2 text-decoration-none   text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700'>Firmar contrato</a>";
                $cosuser[0]["currentStepGen"] = 0;
                $cosuser[0]["currentStepJur"] = -1;
            }
            $cosuser[0]["certificadoDescargado"] = 0;
        }

        foreach ($cosuser as &$co) {
            if(isset($cos[$co['servicio']])){
                $maxGen = sizeof($cos[$co['servicio']]["genealogico"]);
                $maxJur = sizeof($cos[$co['servicio']]["juridico"]);

                $co['progressPercentageGen'] = isset($co['currentStepGen']) && $co['currentStepGen'] >= 0
                    ? round(($co['currentStepGen'] / $maxGen) * 100)
                    : 0;

                $co['progressPercentageJur'] = isset($co['currentStepJur']) && $co['currentStepJur'] >= 0
                    ? round(($co['currentStepJur'] / $maxJur) * 100)
                    : 0;
            }

        }

        unset($co);
        $user->arraycos        = $cosuser;
        $user->arraycos_expire = Carbon::now()->addDays(2);
        $user->save();

        $comprasConDealNoPagadas = Compras::where('deal_id', '!=', null)
                                ->where('pagado', 0)
                                ->where('id_user', $user->id)
                                ->get();

        $comprasSinDealNoPagadas = Compras::whereNull('deal_id')
                                ->where('pagado', 0)
                                ->where('id_user', $user->id)
                                ->get();

        $documentRequests = DocumentRequest::where('user_id', $user->id)
                                   ->latest()
                                   ->get();



        $cosuserFinal = [];
        $duplicadosDetectados = false;

        foreach ($cosuser as $item) {
            $servicio = $item['servicio'];

            if (!isset($cosuserFinal[$servicio])) {
                $cosuserFinal[$servicio] = $item;
            } else {
                $duplicadosDetectados = true;

                // Comparar con el ya guardado
                $existente = $cosuserFinal[$servicio];

                // Se queda con el que tenga los valores más altos de currentStepGen y currentStepJur
                if (
                    $item['currentStepGen'] > $existente['currentStepGen'] ||
                    $item['currentStepJur'] > $existente['currentStepJur']
                ) {
                    $cosuserFinal[$servicio] = $item;
                }
            }
        }

        uasort($cosuserFinal, function ($a, $b) {
            $sumaA = ($a['currentStepGen'] ?? 0) + ($a['currentStepJur'] ?? 0);
            $sumaB = ($b['currentStepGen'] ?? 0) + ($b['currentStepJur'] ?? 0);

            // Orden descendente (de mayor a menor)
            return $sumaB <=> $sumaA;
        });

        $cos = array_cos();

        // Reemplazar $cosuser con la versión final
        $cosuser = array_values($cosuserFinal);

        if ($duplicadosDetectados) {
            try {
                \Mail::raw("REVISA LOS NEGOCIOS DEL CLIENTE ID {$user->id}", function ($message) use ($user) {
                    $message->to('sistemasccs@sefarvzla.com') // <-- cámbialo al real
                            ->subject("Duplicados detectados en COSUSER - Cliente ID {$user->id}");
                });
            } catch (\Exception $e) {
                \Log::error("Error enviando correo a sistemasccs: " . $e->getMessage());
            }
        }

        $user->cosready = 0;
        $user->save();

        foreach ($cosuser as $item) {
            // Normalizar servicio del usuario
            $servicio = trim(mb_strtolower($item['servicio']));

            // Normalizar las llaves del array $cos
            $cosKeys = array_map(fn($k) => mb_strtolower($k), array_keys($cos));

            if (in_array($servicio, $cosKeys)) {
                $user->cosready = 1;
                $user->save();
                break; // basta con que uno coincida
            }
        }

        CosVisit::create([
            'user_id' => auth()->id(),
            'fecha_visita' => now(),
            'cliente_id' => $user->id
        ]);

        $html = view('crud.users.edit', compact('documentRequests', 'comprasConDealNoPagadas', 'comprasSinDealNoPagadas', 'imageUrls', 'cosuser', 'cos', 'servicename', 'negocios', 'usuariosMonday', 'dataMonday', 'mondayData', 'boardId', 'boardName', 'mondayFormBuilder', 'archivos', 'user', 'roles', 'permissions', 'facturas', 'servicios', 'columnasparatabla'))->render();
        return $html;

    }

    /**
     * Verifica si existe un negocio relacionado y si tiene pagos
     *
     * @param Collection $negocios - Todos los negocios del cliente
     * @param string $servicioSolicitado - Nombre exacto del servicio
     * @param array $palabrasClave - Palabras para buscar en dealname
     * @return bool - true si el negocio existe Y tiene pagos, false en caso contrario
     */
    private function verificarNegocioActivo($negocios, $servicioSolicitado, $palabrasClave = []) {
        // Campos que indican que hay un pago
        $camposPago = [
            'fase_0_pagado', 'fase_0_pagado__teamleader_',
            'fase_1_pagado', 'fase_1_pagado__teamleader_', 'fase_1_preestab',
            'fase_2_pagado', 'fase_2_pagado__teamleader_', 'fase_2_preestab',
            'fase_3_pagado', 'fase_3_pagado__teamleader_', 'fase_3_preestab',
            'fcje_pagado', 'fcje_preestab',
            'fecha_fase_0_pagado', 'fecha_fase_1_pagado', 'fecha_fase_2_pagado', 'fecha_fase_3_pagado',
            'monto_fase_1_pagado', 'monto_fase_2_pagado', 'monto_fase_3_pagado'
        ];

        foreach ($negocios as $negocioItem) {
            // Buscar por servicio_solicitado exacto
            $coincideServicio = isset($negocioItem->servicio_solicitado) &&
                            $negocioItem->servicio_solicitado == $servicioSolicitado;

            // Buscar por palabras clave en dealname
            $coincideDealname = false;
            if (!empty($palabrasClave) && isset($negocioItem->dealname)) {
                $dealnameLower = mb_strtolower($negocioItem->dealname);
                $todasCoinciden = true;

                foreach ($palabrasClave as $palabra) {
                    if (strpos($dealnameLower, mb_strtolower($palabra)) == false) {
                        $todasCoinciden = false;
                        break;
                    }
                }

                $coincideDealname = $todasCoinciden;
            }

            // Si encontramos el negocio (por servicio O por dealname)
            if ($coincideServicio || $coincideDealname) {

                // Para este servicio, consideramos que siempre tiene pagos
                foreach ($camposPago as $campo) {
                    if (isset($negocioItem->{$campo}) && !empty($negocioItem->{$campo})) {
                        return true; // ✅ Negocio encontrado Y con pagos
                    }
                }

                // Negocio encontrado pero SIN pagos (está en "interesado")
                return false;

            }
        }

        // No se encontró ningún negocio relacionado
        return false;
    }

    private function obtenerValorPorTitulo($items, $tituloBuscado) {
        // Asegurarse de que hay items y column_values
        if (!isset($items['items'][0]['column_values'])) {
            return null;
        }

        foreach ($items['items'][0]['column_values'] as $columna) {
            if (isset($columna['column']['title']) &&
                strcasecmp(trim($columna['column']['title']), trim($tituloBuscado)) === 0) {
                return $columna['text'] ?? null;
            }
        }

        return null;
    }

    private function syncDealFieldsBetweenPlatforms($hubspotDeals, $teamleaderDeals, $camposRelacionados, $user)
    {
        $updatesToHubspotAll = [];
        $updatesToTeamleaderAll = [];
        $updatesToDBAll = [];

        // Indexar por dealname
        $hubspotByName = collect($hubspotDeals)->keyBy(fn($d) => $d['properties']['dealname'] ?? '');
        $teamleaderByName = collect($teamleaderDeals)->keyBy(fn($d) => $d['title'] ?? '');

        // 1. Crear faltantes en HubSpot
        foreach ($teamleaderByName as $dealName => $tlDeal) {
            if (!$hubspotByName->has($dealName)) {
                try {
                    $newHsDeal = $this->hubspotService->createDealInHubspotFromTL($tlDeal, $user->hs_id ?? null, $camposRelacionados);
                    $hubspotByName->put($dealName, $newHsDeal); // actualiza colección
                    Log::info("Trato creado en HubSpot desde TL: " . $dealName);
                } catch (\Exception $e) {
                    Log::error("Error creando trato en HubSpot desde TL: " . $e->getMessage());
                }
            }
        }

        // 2. Crear faltantes en Teamleader
        foreach ($hubspotByName as $dealName => $hsDeal) {
            if (!$teamleaderByName->has($dealName)) {
                try {
                    $newTLDeal = $this->teamleaderService->createProjectFromHubspotDeal($hsDeal, $user->tl_id, $camposRelacionados);
                    $teamleaderByName->put($dealName, array_merge($newTLDeal, ['title' => $dealName]));
                    Log::info("Trato creado en Teamleader desde HubSpot: " . $dealName);
                } catch (\Exception $e) {
                    Log::error("Error creando trato en TL desde HubSpot: " . $e->getMessage());
                }
            }
        }

        // 3. Sincronizar valores
        foreach ($hubspotByName as $dealName => $hsDeal) {
            $hsDeal['properties']['dealname'] = $dealName;
            $hubspotId = $hsDeal['id'] ?? null;
            $hubspotProps = $hsDeal['properties'] ?? [];

            $tlDeal = $teamleaderByName->get($dealName);
            $teamleaderId = $tlDeal['id'] ?? null;
            $existingFields = $tlDeal['custom_fields'] ?? [];

            $hubspotLastMod = new \DateTime($hubspotProps['lastmodifieddate'] ?? '1970-01-01');
            $teamleaderLastMod = new \DateTime($tlDeal['updated_at'] ?? '1970-01-01');

            $tlCustomFields = [];
            $hsUpdates = [];
            $dbUpdates = [];

            foreach ($camposRelacionados as $hsField => $tlFieldId) {
                $hsValue = $hubspotProps[$hsField] ?? null;
                $tlField = collect($existingFields)->firstWhere('definition.id', $tlFieldId);
                $tlValue = $tlField['value'] ?? null;

                // 🚨 Excepción: HubSpot manda en servicio_solicitado
                if ($hsField === 'servicio_solicitado2') {
                    if (!is_null($hsValue)) {
                        // Guardamos SIEMPRE en DB desde HubSpot
                        $dbUpdates['servicio_solicitado2'] = $hsValue;
                        $dbUpdates['servicio_solicitado']  = $hsValue; // ✅ Mantén sincronizado también servicio_solicitado

                        // Opcional: actualiza también en HubSpot el campo "servicio_solicitado"
                        $hsUpdates['servicio_solicitado'] = $hsValue;

                        // Y en Teamleader
                        $tlCustomFields[] = [
                            'id' => $tlFieldId,
                            'value' => $hsValue
                        ];
                    }
                    continue;
                }

                // --- Lógica normal para los demás campos ---
                $finalValue = null;
                if ($hsValue && (!$tlValue || $hubspotLastMod > $teamleaderLastMod)) {
                    $finalValue = $hsValue;
                } else if ($tlValue) {
                    $finalValue = $tlValue;
                }

                if (!is_null($finalValue)) {
                    $tlCustomFields[] = [
                        'id' => $tlFieldId,
                        'value' => $finalValue
                    ];

                    if ($tlValue && (!$hsValue || $teamleaderLastMod > $hubspotLastMod)) {
                        $hsUpdates[$hsField] = $tlValue;
                    }

                    $dbUpdates[$hsField] = $finalValue;
                }
            }

            $dbUpdates['dealname'] = $dealName;


            if ($teamleaderId && !empty($tlCustomFields)) {
                $updatesToTeamleaderAll[$teamleaderId] = [
                    'custom_fields' => $tlCustomFields
                ];
            }

            if (!empty($hsUpdates) && $hubspotId) {
                $updatesToHubspotAll[$hubspotId] = $hsUpdates;
            }

            if (!empty($dbUpdates)) {
                $updatesToDBAll[] = [
                    'hubspot_id' => $hubspotId,
                    'teamleader_id' => $teamleaderId,
                    'user_id' => $user->id,
                    'fields' => $dbUpdates
                ];
            }
        }

        // Actualizar HubSpot
        foreach ($updatesToHubspotAll as $dealId => $fields) {
            $this->hubspotService->updateDeals($dealId, $fields);
        }

        // Actualizar Teamleader
        foreach ($updatesToTeamleaderAll as $tlDealId => $payload) {
            $this->teamleaderService->updateProject($tlDealId, $payload);
        }

        // Actualizar DB

        foreach ($updatesToDBAll as $entry) {
            $negocio = Negocio::firstOrNew([
                'hubspot_id' => $entry['hubspot_id']
            ]);
            $negocio->user_id = $entry['user_id'];
            $negocio->teamleader_id = $entry['teamleader_id'];

            foreach ($entry['fields'] as $field => $value) {
                if (Schema::hasColumn((new Negocio)->getTable(), $field) && $field != 'documentos') {
                    if (is_array($value)) {
                        // Puedes usar json_encode o implode dependiendo del caso
                        $negocio->{$field} = json_encode($value); // o implode(', ', $value)
                    } else {
                        $negocio->{$field} = $value;
                    }
                }
            }

            $negocio->save();
        }
    }

    protected function processParent(array &$currentGen, array $parent, string $field, string $sex, array $peopleMap): void
    {
        $j = count($currentGen);

        if (empty($parent[$field])) {
            $currentGen[$j]['showbtn'] = ($parent['showbtn'] == 0) ? 0 : 1;
            $currentGen[$j]['showbtnsex'] = $sex;
        } else {
            if (isset($peopleMap[$parent[$field]])) {
                $currentGen[$j] = $peopleMap[$parent[$field]];
                $currentGen[$j]['showbtn'] = 2;
            }
        }
    }

    /**
     * Genera el texto de relación genealógica
     */
    protected function generateRelationshipText(int $i, int $key): string
    {
        $text = "";
        $multiplicador = 4;

        for ($j = 1; $j <= $key; $j++) {
            $text .= (($i % $multiplicador) < ($multiplicador / 2) ? "P " : "M ");
            $multiplicador *= 2;
        }

        $text .= ($i < 2 * ($key + 1) ? "P" : "M");
        return $text;
    }

    private function analizarEtiquetasYDevolverJSON($mondaydataforAI)
    {
        $apiKey = env('OPENROUTER_API_KEY');

        // Construye el prompt dinámicamente con los valores actuales del arreglo
        $inputJSON = json_encode([
            'tablero' => $mondaydataforAI['tablero'] ?? '',
            'etiquetas' => $mondaydataforAI['etiquetas'] ?? '',
            //'información_genealogia' => $mondaydataforAI['información_genealogia'] ?? '',
            //'información_ventas' => $mondaydataforAI['información_ventas'] ?? '',
        ], JSON_UNESCAPED_UNICODE);

        $mensaje = [
            [
                "role" => "system",
                "content" => "Eres una IA especializada en genealogía legal. Evalúa el siguiente objeto y responde SOLO con un JSON con claves booleanas. No agregues explicación. El JSON será procesado automáticamente por backend."
            ],
            [
                "role" => "user",
                "content" => "
                        INPUT:

                        Nombre del tablero: {$mondaydataforAI['tablero']}
                        Etiquetas: " . ($mondaydataforAI['etiquetas'] ?? 'NO TIENE ETIQUETAS TODAVIA'). "

                        REGLAS:

                        1. **otrosProcesos**: 'true' si las etiquetas incluyen 'no apto', 'apto para otros procesos' o similares.
                        2. **pericial**: 'true' si alguna etiqueta contiene 'Informe Pericial' o 'Defensa Jurídica'.
                        3. **genealogiaAprobada**: 'true' si alguna etiqueta contiene 'aprobado' o 'aceptado' algo que indique aprobación explícita de genealogía.
                        4. **genealogia**: 'true' si 'genealogiaAprobada' es true.
                        5. **investigacionProfunda**: 'true' si hay una etiqueta con 'Investigación más profunda'.
                        6. **investigacionInSitu**: 'true' si hay una etiqueta con 'Investigación in situ'.
                        7. **analisisYCorreccion**: Devuelve 'true' si hay evidencia de que se realizó análisis o corrección del árbol genealógico. Para esto, revisa si existen campos como 'Solicitud cliente', 'respuesta de la Solicitud', o si se indica que el 'Arbol fue Cargado' en el campo de Arbol Cargado.
                        NOTA: Solicitud cliente y respuesta de la solicitud son campos que se encuentran en el tablero 'Analisis preliminar'. Si el nombre del tablero no es ese, entonces, analisisYCorreccion será false.
                        8. **investigacionIntuituPersonae**: Devuelve 'true' si el tablero actual es Análisis. De resto, es 'false'.
                        9. **inicioInvestigacion**: Devuelve 'true' si el tablero actual es 'Análisis' (ojo, no 'Analisis preliminar'). De resto, es 'false'.
                        Ejemplo de respuesta esperada:
                        {
                            'otrosProcesos': false,
                            'pericial': true,
                            'genealogiaAprobada': false,
                            'genealogia': true,
                            'investigacionProfunda': false,
                            'investigacionInSitu': true,
                            'analisisYCorreccion': true,
                            'investigacionIntuituPersonae': false
                        }
                    "
            ]
        ];

        // Llamada a OpenRouter
        $response = Http::withHeaders([
            'Authorization' => "Bearer $apiKey",
            'Content-Type' => 'application/json',
        ])->post("https://openrouter.ai/api/v1/chat/completions", [
            'model' => 'openai/gpt-4.1-mini', // o GPT compatible
            'messages' => $mensaje
        ]);

        if ($response->successful()) {
            $json = $response->json()['choices'][0]['message']['content'];

            // Intentar decodificar el JSON
            $resultado = json_decode($json, true);

            if (is_array($resultado)) {
                return $resultado;
            } else {
                Log::warning("Respuesta IA no válida: $json");
                return [];
            }
        }

        // Fallback si la IA falla
        return [];
    }

    public function savePersonalData(Request $request){
        $request->validate([
            'email' => 'required|email|unique:users,email,' . $request->id,
            'phone' => 'required|string|max:15',
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'passport' => 'required|string|max:20|unique:users,passport,' . $request->id,
        ], [
            'correo.required' => 'El campo correo es obligatorio.',
            'correo.email' => 'El correo debe ser válido.',
            'correo.unique' => 'Este correo ya está registrado.',
            'phone.required' => 'El campo teléfono es obligatorio.',
            'nombres.required' => 'El campo nombres es obligatorio.',
            'apellidos.required' => 'El campo apellidos es obligatorio.',
            'date_of_birth.required' => 'El campo fecha de nacimiento es obligatorio.',
            'date_of_birth.date' => 'La fecha de nacimiento debe ser una fecha válida.',
            'passport.required' => 'El campo pasaporte es obligatorio.',
            'passport.unique' => 'Este pasaporte ya está registrado.',
        ]);

        $hubspotFields = [
            'fecha_nac' => 'date_of_birth',
            'nombres' => 'firstname',
            'updated_at' => 'lastmodifieddate',
            'apellidos' => 'lastname' ,
            'referido_por' => 'n000__referido_por__clonado_',
            'passport' => 'numero_de_pasaporte',
            'servicio' => 'servicio_solicitado',
        ];

        $user = User::findOrFail($request->id);

        // Obtener los datos actuales de la base de datos
        $currentData = $user->toArray();

        // Filtrar el request eliminando valores NULL que ya son NULL en la base de datos
        $filteredRequest = collect($request->all())
            ->filter(function ($value, $key) use ($currentData) {
                return !is_null($value) || !array_key_exists($key, $currentData) || !is_null($currentData[$key]);
            })
            ->except(['_token', 'id']);

        if ($filteredRequest->has('vinculo_antepasados')) {
            $filteredRequest['vinculo_antepasados'] = implode(';', $filteredRequest->get('vinculo_antepasados'));
        }

        $hubspotData = [];

        foreach ($filteredRequest as $key=>$data){
            if ($key != "pay" && $key != "contrato"){
                if (isset($hubspotFields[$key])){
                    $hubspotData[$hubspotFields[$key]] = $data;
                } else {
                    $hubspotData[$key] = $data;
                }
            }
        }

        // Inspeccionar resultados
        $user->update($filteredRequest->toArray());

        // Llamar a la API de HubSpot para actualizar los datos
        $this->hubspotService->updateContact($user->hs_id, $hubspotData);

        // Retornar respuesta exitosa
        return response()->json(['message' => 'Datos actualizados correctamente.']);
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
        $user->pay = $request->pay;
        $user->servicio = $request->servicio;
        $user->contrato = $request->contrato;
        if($request->two_factor){
            $user->two_factor_secret = null;
            $user->two_factor_recovery_codes = null;
        }
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

    public function fixpassport()
    {
        return view('crud.users.fix');
    }

    public function fixpassportprocess(Request $request)
    {
        $bad_passport = trim($request->oldpass);
        $good_passport = trim($request->newpass);

        $user = json_decode(json_encode(DB::table('users')->where('passport', $bad_passport)->get()), true);

        if ( count($user) == 0 ){
            return redirect()->route('fixpassport')->with(['error' => 'No hay información registrada en la base de datos con el pasaporte '. $bad_passport ."."]);
        }

        DB::table('users')->where('passport', $good_passport)->update(['passport' => $good_passport."X"]);
        DB::table('users')->where('passport', $bad_passport)->update(['passport' => $good_passport]);

        DB::table('agclientes')->where('IDCliente', $good_passport)->update(['IDCliente' => $good_passport."X"]);
        DB::table('agclientes')->where('IDCliente', $bad_passport)->update(['IDCliente' => $good_passport]);

        DB::table('files')->where('IDCliente', $good_passport)->update(['IDCliente' => $good_passport."X"]);
        DB::table('files')->where('IDCliente', $bad_passport)->update(['IDCliente' => $good_passport]);

        DB::table('users')->where('passport', $good_passport."X")->delete();
        DB::table('agclientes')->where('IDCliente', $good_passport."X")->delete();
        DB::table('files')->where('IDCliente', $good_passport."X")->delete();

        return redirect()->route('fixpassport')->with(['success' => 'Se ha arreglado satisfactoriamente el pasaporte del cliente ' . $user[0]["name"] . '. Verifique su arbol <a href="/tree/'.$good_passport.'">aquí</a>.']);
    }

    public function getemail(Request $request)
    {
        $data = $request->id;
        $email = json_decode(json_encode(User::where('passport', 'LIKE', '%'.$data.'%')->get()),true);
        if (sizeof($email)>0){
            print_r($email[0]['email']);
        }
    }

    private function searchUserInMonday($passport, User $user)
    {
        $client = new Client();  // Inicializa el cliente Guzzle
        $boardIds = [
            878831315,
            6524058079, 3950637564, 815474056, 3639222742, 3469085450, 2213224176,
            1910043474, 1845710504, 1845706367, 1845701215, 1016436921,
            1026956491, 815474056, 815471640, 807173414,
            803542982, 765394861, 742896377, 708128239, 708123651,
            669590637, 625187241
        ];
        $searchUrl = "https://app.sefaruniversal.com/tree/" . $passport;

        $promises = [];  // Array para almacenar las promesas de las solicitudes

        foreach ($boardIds as $boardId) {
            $query = "
                query {
                    items_page_by_column_values(
                        limit: 50,
                        board_id: {$boardId},
                        columns: [{column_id: \"enlace\", column_values: [\"{$searchUrl}\"]}]
                    ) {
                        cursor
                        items {
                            id
                        }
                    }
                }
            ";

            // Cada solicitud se hace de manera asíncrona
            $promises[] = $client->postAsync("https://api.monday.com/v2", [
                'json' => ['query' => $query],
                'headers' => [
                    "Authorization" => "Bearer " . getenv('MONDAY_TOKEN'),
                    "Content-Type" => "application/json"
                ]
            ]);
        }

        // Esperar todas las respuestas y procesarlas
        $responses = Promise\Utils::settle($promises)->wait();

        foreach ($responses as $response) {
            if ($response['state'] === 'fulfilled') {
                $data = json_decode($response['value']->getBody(), true);

                if (!empty($data['data']['items_page_by_column_values']['items'])) {
                    $item = $data['data']['items_page_by_column_values']['items'][0];
                    $user->monday_id = $item['id']; // Guardar el ID de Monday
                    $user->save();
                    return $item;  // Devolver el primer item encontrado
                }
            }
        }

        return null;  // Si no se encuentra el usuario en ningún board
    }

    private function storeMondayBoardColumns($boardId)
    {
        try {
            // Step 1: Fetch only basic column info (lightweight query)
            $metadataQuery = 'query { boards(ids: [' . $boardId . ']) { columns { id title type } } }';
            $metadataResult = Monday::customQuery($metadataQuery);

            if (empty($metadataResult['data']['boards'][0]['columns'])) {
                return; // No columns found
            }

            $columns = $metadataResult['data']['boards'][0]['columns'];
            $filteredColumns = [];
            $excludedTypes = [
                'name', 'subtasks', 'auto_number', 'progress',
                'creation_log', 'link', 'integration', 'item_id',
                'formula', 'board_relation', 'mirror', 'email'
            ];

            // Filter columns to only include relevant ones
            foreach ($columns as $column) {
                if (!in_array($column['type'], $excludedTypes)) {
                    $filteredColumns[] = $column['id'];
                }
            }

            if (empty($filteredColumns)) {
                return; // No relevant columns found
            }

            // Step 2: Fetch full details only for relevant columns
            $columnsQuery = 'query {
                boards(ids: [' . $boardId . ']) {
                    columns(ids: [' . implode(',', array_map('json_encode', $filteredColumns)) . ']) {
                        id
                        title
                        type
                        settings_str
                    }
                }
            }';

            $fullResult = Monday::customQuery($columnsQuery);
            $relevantColumns = $fullResult['data']['boards'][0]['columns'] ?? [];

            $batchData = [];
            foreach ($relevantColumns as $column) {
                $settings = !empty($column['settings_str']) ? json_decode($column['settings_str'], true) : [];

                $tagIds = [];
                if (in_array($column['type'], ['tags', 'multi-select']) && !empty($settings['tags'])) {
                    $tagIds = array_column($settings['tags'], 'id');
                }

                $batchData[] = [
                    'board_id' => $boardId,
                    'column_id' => $column['id'],
                    'title' => $column['title'],
                    'type' => $column['type'],
                    'settings' => $column['settings_str'] ?? null,
                    'tag_ids' => $tagIds,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            // Batch upsert
            if (!empty($batchData)) {
                MondayFormBuilder::upsert(
                    $batchData,
                    ['board_id', 'column_id'],
                    ['title', 'type', 'settings', 'tag_ids', 'updated_at']
                );
            }

        } catch (\Exception $e) {
            Log::error("Failed to store Monday board columns: " . $e->getMessage());
            // Optionally rethrow if needed
            // throw $e;
        }
    }

    private function storeMondayUserData($user, $mondayUserDetailsPre)
    {
        MondayData::updateOrCreate(
            ['user_id' => $user->id],
            ['data' => json_encode($mondayUserDetailsPre)]
        );
    }

    public function getUsersForSelect()
    {
        // Consulta GraphQL para obtener los usuarios
        $query = '
        users {
            id
            name
            email
            enabled
        }';

        // Ejecuta la consulta (suponiendo que tienes un método para esto)
        $users = Monday::customQuery($query);

        // Filtra los usuarios habilitados
        $enabledUsers = collect($users['users'])
            ->filter(fn($user) => $user['enabled']) // Solo usuarios habilitados
            ->map(fn($user) => [
                'id'   => $user['id'],
                'name' => $user['name'],
                'email' => $user['email']
            ])
            ->values();

        // Devuelve los usuarios listos para usarse en un select
        return response()->json($enabledUsers);
    }

}
