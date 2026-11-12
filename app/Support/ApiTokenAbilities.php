<?php

namespace App\Support;

class ApiTokenAbilities
{
    public const LABELS = [
        'read' => 'Lectura',
        'create' => 'Crear',
        'update' => 'Actualizar',
        'cos:refresh' => 'Actualizar COS sin caché',
        'client-notes:create' => 'Crear notas de clientes',
        'client-blocks:read' => 'Comprobar permiso de entrega a clientes',
        'client-blocks:sync' => 'Comprobar retiro de clientes desde HubSpot',
        'brightbean:sync' => 'BrightBean: sincronizar mensajes',
        'brightbean:ai' => 'BrightBean: generar respuestas con IA',
        'delete' => 'Eliminar',
        'whatsapp:read' => 'WhatsApp: consultar mensajes pendientes',
        'whatsapp:update' => 'WhatsApp: registrar envíos y errores',
        'tasks:create' => 'n8n: crear tareas',
        'cron:scheduler:run' => 'Cron: ejecutar el scheduler',
        'cron:queue:work' => 'Cron: procesar la cola general',
        'cron:followups:run' => 'Cron: enviar seguimientos de registro',
        'cron:reports:generate' => 'Cron: generar reportes',
        'cron:teamleader:sync' => 'Cron: sincronizar Teamleader',
        'cron:teamleader:work' => 'Cron: procesar la cola de Teamleader',
        'cron:tasks:work' => 'Cron: procesar la cola de tareas',
        'cron:jotform:sync' => 'Cron: sincronizar cupones Jotform',
        'tasks:workflow' => 'Cron: ejecutar el workflow diario de tareas',
        'deploy:run' => 'Deploy: ejecutar despliegue (solo administradores)',
    ];
}
