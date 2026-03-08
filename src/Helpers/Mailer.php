<?php
// CLASE AYUDANTE (HELPER): Define la estructura estática para gestionar y estandarizar el envío de correos electrónicos a través de SMTP.
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    
    // PROPIEDAD ESTÁTICA: Almacena la configuración en memoria para no tener que leer el archivo en cada envío consecutivo de correos.
    private static $config;

    // INICIALIZADOR: Carga las credenciales SMTP desde el archivo de configuración solo si aún no han sido cargadas previamente.
    private static function init() {
        if (self::$config === null) {
            self::$config = include __DIR__ . '/../../config/mail.php';
        }
    }

    // DESPACHADOR CENTRAL: Método estático que envía el correo y ahora acepta un parámetro ($isHtml) para soportar plantillas visuales.
    public static function enviarCorreo($destinatario, $asunto, $mensaje, $isHtml = true) {
        
        // CARGA DE CREDENCIALES: Invoca al inicializador para asegurar que los datos del servidor SMTP estén listos antes de intentar conectar.
        self::init();
        
        // INSTANCIA DE CORREO: Crea el objeto PHPMailer activando las excepciones (true) para poder capturar y manejar errores de conexión.
        $mail = new PHPMailer(true);
        
        try {
            // BYPASS PARA XAMPP LOCAL: Desactiva temporalmente la verificación estricta de certificados SSL que suele bloquear correos en entornos de prueba.
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // CONFIGURACIÓN DE PROTOCOLO: Indica explícitamente a la librería que utilizará el protocolo SMTP para comunicarse con el servidor (ej. Google).
            $mail->isSMTP();
            $mail->Host       = self::$config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = self::$config['username'];
            $mail->Password   = self::$config['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = self::$config['port'];

            // REMITENTE Y DESTINO: Define la identidad oficial del sistema (De) y añade la dirección electrónica del receptor final (Para).
            $mail->setFrom(self::$config['from_email'], self::$config['from_name']);
            $mail->addAddress($destinatario);

            // FORMATO DE CONTENIDO: Configura dinámicamente si el cuerpo del mensaje será renderizado con etiquetas HTML o como texto plano estándar.
            $mail->isHTML($isHtml);
            $mail->Subject = $asunto;
            $mail->Body    = $mensaje;

            // EJECUCIÓN DE ENVÍO: Lanza la petición al servidor de correo y retorna un valor booleano 'true' si el mensaje fue aceptado.
            $mail->send();
            return true;
            
        } catch (Exception $e) {
            // MANEJO DE ERRORES REAL: En lugar de un fallo silencioso (false), devuelve el mensaje técnico exacto de PHPMailer para facilitar la depuración.
            return $mail->ErrorInfo; 
        }
    }
}
?>