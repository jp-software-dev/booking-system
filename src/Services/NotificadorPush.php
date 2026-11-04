<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Models\SuscripcionPush;
use App\Models\Tratamiento;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

/**
 * NOTIFICACIONES PUSH DEL NAVEGADOR (WEB PUSH)
 *
 * Envía avisos a los navegadores en los que el paciente aceptó notificaciones,
 * aunque tenga MediAgenda cerrada. Cada aviso se cifra para ese navegador y se
 * firma con las claves VAPID del .env (VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY y
 * VAPID_SUBJECT), que identifican a este servidor ante el servicio push.
 *
 * enviarPendientes() lo llama cada minuto api/enviar_recordatorios.php (en la
 * demo, una tarea pg_cron de Supabase) o database/enviar_recordatorios.php (CLI).
 */
final class NotificadorPush
{
    /** Segundos que el servicio push guarda un aviso si el teléfono está apagado. */
    private const VIGENCIA_SEGUNDOS = 3600;

    /** Antigüedad máxima de una toma para avisarla (minutos). */
    private const VENTANA_MINUTOS = 15;

    /**
     * Indica si las claves VAPID están configuradas.
     */
    public static function configurado(): bool
    {
        return self::clavePublica() !== '' && (string) Env::get('VAPID_PRIVATE_KEY', '') !== '';
    }

    /**
     * Clave pública VAPID (la necesita el navegador para suscribirse).
     */
    public static function clavePublica(): string
    {
        return (string) Env::get('VAPID_PUBLIC_KEY', '');
    }

    /**
     * Envía un aviso a todos los navegadores suscritos del paciente.
     *
     * @param int                                                       $idPaciente Paciente.
     * @param array{titulo: string, cuerpo: string, url: string, etiqueta: string} $aviso
     * @return array{enviados: int, fallidos: int, vencidas: int}
     */
    public function enviarAPaciente(int $idPaciente, array $aviso): array
    {
        $suscripciones = new SuscripcionPush();
        $resultado = ['enviados' => 0, 'fallidos' => 0, 'vencidas' => 0];
        $destinos = $suscripciones->listarPorPaciente($idPaciente);

        if ($destinos === []) {
            return $resultado;
        }

        $webPush = $this->crearCliente();
        $contenido = (string) json_encode($aviso, JSON_UNESCAPED_UNICODE);

        foreach ($destinos as $destino) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $destino['endpoint'],
                'keys'     => ['p256dh' => $destino['clave_p256dh'], 'auth' => $destino['clave_auth']],
            ]), $contenido);
        }

        foreach ($webPush->flush() as $reporte) {
            $endpoint = (string) $reporte->getEndpoint();

            if ($reporte->isSuccess()) {
                $resultado['enviados']++;
                $suscripciones->marcarEnvio($endpoint);
            } elseif ($reporte->isSubscriptionExpired()) {
                // El navegador ya no existe o retiró el permiso: se borra la suscripción.
                $resultado['vencidas']++;
                $suscripciones->eliminarVencida($endpoint);
            } else {
                $resultado['fallidos']++;
                error_log('Push no enviado: ' . $reporte->getReason());
            }
        }

        return $resultado;
    }

    /**
     * Avisa de las tomas que llegaron a su hora. Las tomas de un paciente a la
     * misma hora se agrupan en un solo aviso. Cada toma se marca como avisada
     * aunque el paciente no tenga navegadores suscritos, para no repetirla.
     *
     * @return array{tomas: int, avisos: int, enviados: int, fallidos: int, vencidas: int}
     */
    public function enviarPendientes(): array
    {
        $tratamientos = new Tratamiento();
        $pendientes = $tratamientos->pendientesDeAviso(self::VENTANA_MINUTOS);
        $resumen = ['tomas' => count($pendientes), 'avisos' => 0, 'enviados' => 0, 'fallidos' => 0, 'vencidas' => 0];

        // Agrupa por paciente y hora exacta.
        $grupos = [];
        foreach ($pendientes as $toma) {
            $grupos[$toma['id_paciente'] . '|' . $toma['programada_en']][] = $toma;
        }

        foreach ($grupos as $tomas) {
            $hora = substr((string) $tomas[0]['programada_en'], 11, 5);
            $nombres = array_map(static fn (array $t): string => (string) $t['medicamento'], $tomas);
            $cuerpo = count($tomas) === 1
                ? "{$tomas[0]['medicamento']} · {$tomas[0]['dosis']}"
                : implode(', ', array_slice($nombres, 0, -1)) . ' y ' . end($nombres);

            if (self::configurado()) {
                $envio = $this->enviarAPaciente((int) $tomas[0]['id_paciente'], [
                    'titulo'   => "Hora de tu medicamento ({$hora})",
                    'cuerpo'   => $cuerpo,
                    'url'      => 'tratamientos.php',
                    'etiqueta' => 'toma-' . $tomas[0]['id_paciente'] . '-' . str_replace([' ', ':'], '-', (string) $tomas[0]['programada_en']),
                ]);
                foreach ($envio as $clave => $cantidad) {
                    $resumen[$clave] += $cantidad;
                }
            }

            foreach ($tomas as $toma) {
                $tratamientos->marcarNotificada((int) $toma['id_toma']);
            }
            $resumen['avisos']++;
        }

        return $resumen;
    }

    /**
     * Cliente de Web Push con las claves VAPID del .env.
     *
     * @throws RuntimeException Si faltan las claves.
     */
    private function crearCliente(): WebPush
    {
        if (!self::configurado()) {
            throw new RuntimeException('Faltan las claves VAPID en el .env');
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject'    => (string) Env::get('VAPID_SUBJECT', 'mailto:contacto@example.com'),
                'publicKey'  => self::clavePublica(),
                'privateKey' => (string) Env::get('VAPID_PRIVATE_KEY', ''),
            ],
        ], ['TTL' => self::VIGENCIA_SEGUNDOS, 'urgency' => 'high'], new Psr18Client(HttpClient::create(['timeout' => 10])));
        $webPush->setReuseVAPIDHeaders(true);

        return $webPush;
    }
}
