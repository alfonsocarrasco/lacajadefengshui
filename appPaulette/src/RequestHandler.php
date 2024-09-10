<?php

namespace Lacajadefengshuidepaulette\Appsubcribers;

use GuzzleHttp\Client;
use PDO;
use PDOException;

class RequestHandler {
    private $requestUri;
    private $requestMethod;
    private $inputData;
    private $db;

    public function __construct() {
        $this->loadEnv();
        $this->requestUri = $_SERVER['REQUEST_URI'];
        $this->requestMethod = $_SERVER['REQUEST_METHOD'];
        $this->dbConnect(); // Conectar a la base de datos
    }

    // Cargar variables del archivo .env
    private function loadEnv() {
        
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        
        $dotenv->load();
    }

    // Conectar a la base de datos usando PDO
private function dbConnect() {
        $host = $_ENV['DB_HOST'];
        $dbname = $_ENV['DB_NAME'];
        $user = $_ENV['DB_USER'];
        $pass = $_ENV['DB_PASS'];

        try {
            $this->db = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $this->respond(500, "Database connection failed: " . $e->getMessage());
        }
    }

    public function handleRequest() {
        $this->handleValidRoute();
    }

    private function handleValidRoute() {
        switch ($this->requestMethod) {
            case 'GET':
                $this->respond(200, "GET request successful");
                break;
            case 'POST':
                $this->handlePostRequest();
                break;
            case 'PATCH':
                $this->respond(200, "PATCH request successful");
                break;
            case 'DELETE':
                $this->respond(200, "DELETE request successful");
                break;
            default:
                $this->respond(405, "Method Not Allowed");
                break;
        }
    }

    // Método privado para manejar las solicitudes POST
    private function handlePostRequest() {
        $this->inputData = $this->captureAndValidatePayload();

        if ($this->inputData === false) {
            $this->respond(400, "Invalid input data");
        } else {
            $uuid = $this->generateUUID();
            if ($this->saveToDatabase($this->inputData, $uuid)) {
                //$this->sendEmailNotification($this->inputData, $uuid);
                $this->redirectBasedOnTags($this->inputData['tags']);
            } else {
                $this->respond(500, "Failed to save data");
            }
        }
    }

    // Método para capturar y validar el payload
    private function captureAndValidatePayload() {
        $filters = [
            'name' => FILTER_SANITIZE_STRING,
            'email' => FILTER_VALIDATE_EMAIL,
            'notes' => FILTER_SANITIZE_STRING,
            'tags' => FILTER_SANITIZE_STRING
        ];

        $validatedData = filter_input_array(INPUT_POST, $filters);

        if (in_array(false, $validatedData, true)) {
            return false; // Si alguna validación falla
        }

        return $validatedData;
    }

    // Guardar en la base de datos
    private function saveToDatabase($data, $uuid) {
        try {
            $stmt = $this->db->prepare("INSERT INTO customers (uuid, name, email, notes, tags) VALUES (:uuid, :name, :email, :notes, :tags)");
            $stmt->bindParam(':uuid', $uuid);
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':notes', $data['notes']);
            $stmt->bindParam(':tags', $data['tags']);
            return $stmt->execute();
        } catch (PDOException $e) {
            $this->respond(500, "Database error: " . $e->getMessage());
            return false;
        }
    }

    // Generar UUID
    private function generateUUID() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    // Redirigir basado en los tags
    private function redirectBasedOnTags($tags) {
        if (strpos($tags, 'asesoria_empresa') !== false || strpos($tags, 'asesoria_casa') !== false) {
            $redirectUrl = $_ENV['REDIRECT_ASESORIA'];
        } elseif (strpos($tags, 'newsletter') !== false) {
            $redirectUrl = $_ENV['REDIRECT_NEWSLETTER'];
        } else {
            $this->respond(400, "Unknown tag, no redirection set");
        }

        header("Location: $redirectUrl");
        exit;
    }

    // Enviar correo electrónico
    private function sendEmailNotification($data, $uuid) {
        $client = new Client();
        $templatePath = getenv('MAIL_TEMPLATE_PATH');
        $emailTemplate = file_get_contents($templatePath);

        $emailContent = str_replace(['{{name}}', '{{uuid}}'], [$data['name'], $uuid], $emailTemplate);

        $postData = [
            'key' => getenv('MANDRILL_API_KEY'),
            'message' => [
                'from_email' => getenv('MAIL_FROM_ADDRESS'),
                'from_name' => getenv('MAIL_FROM_NAME'),
                'to' => [
                    [
                        'email' => $data['email'],
                        'name' => $data['name'],
                        'type' => 'to'
                    ]
                ],
                'subject' => 'Thank you for your submission!',
                'html' => $emailContent
            ]
        ];

        try {
            $response = $client->post('https://mandrillapp.com/api/1.0/messages/send.json', [
                'json' => $postData
            ]);

            if ($response->getStatusCode() != 200) {
                $this->respond(500, "Failed to send email: HTTP " . $response->getStatusCode());
            }
        } catch (\Exception $e) {
            $this->respond(500, "Failed to send email: " . $e->getMessage());
        }
    }

    // Responder con JSON
    private function respond($statusCode, $message, $data = null) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        $response = ["status" => $statusCode, "message" => $message];

        if ($data) {
            $response["data"] = $data;
        }

        echo json_encode($response);
        exit;
    }
}
