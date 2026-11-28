@php
    $total = $tasks->count();
    $dateLabel = $date->format('d/m/Y');
    $preheader = "Tienes {$total} tarea(s) en progreso desde hace {$days}+ dias.";
@endphp
@component('mail.layouts.base', ['contentRows' => true, 'badge' => $dateLabel])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
<tr>
              <td style="padding:28px 28px 8px 28px;  color:#0F172A;">
                <div style="font-size:14px; color:#64748B; margin-bottom:10px;">
                  Hola {{ $advisor->name }},
                </div>
                <div style="font-size:25px; line-height:1.25; font-weight:800; margin-bottom:10px;">
                  Tienes {{ $total }} tarea(s) en progreso desde hace {{ $days }} dias o mas
                </div>
                <div style="font-size:15px; line-height:1.7; color:#334155;">
                  Estas tareas no se reasignan automaticamente mientras sigan en progreso. Por favor actualiza la gestion o cierralas si ya tienes resultado.
                </div>
              </td>
            </tr>

            <tr>
              <td style="padding:18px 28px 2px 28px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                  @foreach($tasks as $task)
                    @php
                        $contact = $task->contact;
                        $taskUrl = route('tasks.show', $task);
                        $referenceDate = $task->due_date ?: $task->created_at;
                        $ageDays = $referenceDate ? $referenceDate->diffInDays($date) : null;
                    @endphp
                    <tr>
                      <td style="padding:0 0 10px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E2E8F0; border-radius:14px; background:#FFFFFF;">
                          <tr>
                            <td style="padding:16px 16px 12px 16px; ">
                              <div style="font-size:15px; font-weight:800; color:#0F172A; line-height:1.35;">
                                {{ $contact?->name ?? $task->title }}
                              </div>
                              @if($contact?->email)
                                <div style="font-size:13px; color:#64748B; margin-top:5px;">
                                  {{ $contact->email }}
                                </div>
                              @endif
                              <div style="font-size:13px; color:#475569; margin-top:8px; line-height:1.5;">
                                Referencia: {{ $referenceDate ? $referenceDate->format('d/m/Y') : '-' }}
                                @if(! is_null($ageDays))
                                  - {{ $ageDays }} dia(s) en progreso
                                @endif
                              </div>
                              <div style="font-size:13px; color:#475569; margin-top:6px; line-height:1.5;">
                                {{ $task->description ?: 'Sin descripcion adicional.' }}
                              </div>
                              <div style="margin-top:12px;">
                                <a href="{{ $taskUrl }}" style="display:inline-block; background:#2563EB; color:#FFFFFF; text-decoration:none; font-size:13px; font-weight:800; padding:9px 13px; border-radius:10px;">
                                  Abrir tarea
                                </a>
                              </div>
                            </td>
                          </tr>
                        </table>
                      </td>
                    </tr>
                  @endforeach
                </table>
              </td>
            </tr>

            <tr>
              <td align="center" style="padding:12px 28px 26px 28px;">
                <a href="{{ $tasksUrl }}" style="display:inline-block; background:#0B3D4F; color:#FFFFFF; text-decoration:none;  font-size:15px; font-weight:800; padding:13px 20px; border-radius:12px;">
                  Ver mis tareas
                </a>
                <div style=" font-size:12px; line-height:1.6; color:#94A3B8; margin-top:14px;">
                  Este correo fue generado automaticamente por App Sefar - COS.
                </div>
              </td>
            </tr>
</table>
@endcomponent
