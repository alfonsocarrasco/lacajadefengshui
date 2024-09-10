<?php

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
        if (file_exists(__DIR__ . '/.env')) {
            $lines = file(__DIR__ . '/.env');
            foreach ($lines as $line) {
                if (trim($line) !== '') {
                    putenv(trim($line));
                }
            }
        }
    }

    // Conectar a la base de datos usando PDO
    private function dbConnect() {
        $host = getenv('DB_HOST');
        $dbname = getenv('DB_NAME');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASS');

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
        // Capturamos y validamos el payload
        $this->inputData = $this->captureAndValidatePayload();

        if ($this->inputData === false) {
            $this->respond(400, "Invalid input data");
        } else {
            // Generar un UUID y guardar los datos en la base de datos
            $uuid = $this->generateUUID();
            if ($this->saveToDatabase($this->inputData, $uuid)) {
                $this->respond(200, "POST request successful and data saved", ["subscriber_code" => $uuid]);
            } else {
                $this->respond(500, "Failed to save data");
            }
        }
    }

    // Método privado para capturar y validar el payload
    private function captureAndValidatePayload() {
        // Aquí definimos las reglas de validación para los datos del formulario
        $filters = [
            'name' => FILTER_SANITIZE_STRING,
            'email' => FILTER_VALIDATE_EMAIL,
            'notes' => FILTER_SANITIZE_STRING,
            'tags' => FILTER_SANITIZE_STRING
        ];

        // Validamos los datos enviados por $_POST
        $validatedData = filter_input_array(INPUT_POST, $filters);

        // Verificamos si hay algún error de validación
        if (in_array(false, $validatedData, true)) {
            return false; // Si alguna validación falla
        }

        return $validatedData; // Retornamos los datos validados
    }

    // Método para guardar datos validados en la base de datos junto con el UUID
    private function saveToDatabase($data, $uuid) {
        try {
            $stmt = $this->db->prepare("INSERT INTO subscribers (uuid, name, email, notes, tags) VALUES (:uuid, :name, :email, :notes, :tags)");
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

    // Método para generar un UUID
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

    // Método para enviar la respuesta
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

// Crear una instancia del manejador de solicitudes y manejar la solicitud
$requestHandler = new RequestHandler();
$requestHandler->handleRequest();
