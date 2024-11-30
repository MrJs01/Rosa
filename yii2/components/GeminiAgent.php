<?php

namespace app\components;

use Yii;
use yii\base\Component;
use yii\base\Exception;
use yii\httpclient\Client;
class GeminiAgent extends Component
{
    public $apiKey;
    public $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
    public $retryDelay = 15;
    public $maxRetries = 3;

    /**
     * Função principal para processar a imagem e o prompt.
     */
    public function processImageAndPrompt($imagePath, $prompt)
    {
        $encodedImage = $this->encodeImageToBase64($imagePath);

        if (!$encodedImage) {
            echo json_encode($encodedImage);
            return;
            // throw new \Exception("Erro ao codificar a imagem para Base64.");
        }

        // Chamando os agentes
        $agent1Result = $this->retry(function () use ($encodedImage, $prompt) {
            return $this->agent1($encodedImage, $prompt);
        });

        $agent2Result = $this->retry(function () use ($agent1Result) {
            return $this->agent2($agent1Result);
        });

        $agent3Result = $this->retry(function () use ($agent1Result, $agent2Result) {
            return $this->agent3($agent1Result, $agent2Result);
        });

        return [
            'agent1' => $agent1Result,
            'agent2' => $agent2Result,
            'agent3' => $agent3Result,
        ];
    }

    /**
     * Agente 1: Descrição da imagem e plano detalhado.
     */
    private function agent1($encodedImage, $prompt)
    {
        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt],
                        [
                            "inline_data" => [
                                "mime_type" => "image/jpeg",
                                "data" => $encodedImage,
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return $this->sendRequest("gemini-1.5-flash:generateContent", $payload);
    }

    /**
     * Agente 2: Geração da página em HTML usando Bootstrap.
     */
    private function agent2($agent1Result)
    {
        $prompt = "Crie uma página completa em HTML usando Bootstrap com base neste plano: " . $agent1Result['text'];
        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt],
                    ]
                ]
            ]
        ];

        return $this->sendRequest("gemini-1.5-flash:generateContent", $payload);
    }

    /**
     * Agente 3: Resumo detalhado.
     */
    private function agent3($agent1Result, $agent2Result)
    {
        $prompt = "Resuma detalhadamente o que foi feito com base nos seguintes resultados:\n\n" .
            "Descrição e plano do agente 1: " . $agent1Result['text'] . "\n\n" .
            "Código HTML gerado pelo agente 2: " . $agent2Result['text'];

        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt],
                    ]
                ]
            ]
        ];

        return $this->sendRequest("gemini-1.5-flash:generateContent", $payload);
    }

    /**
     * Envia uma requisição para a API do Gemini.
     */
    private function sendRequest($endpoint, $payload)
{
    // URL base da API
    $url = $this->apiUrl . $endpoint;

    // Converte o payload para JSON
    $jsonPayload = json_encode($payload);

    // Inicializa o cURL
    $ch = curl_init($url);

    // Configurações do cURL
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Retorna o resultado como string
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json', // Tipo de conteúdo
        'Authorization: Bearer AIzaSyA2ksuP2s0OO6USa_Fws2EULDP2EGbQPdQ', // Autenticação com Bearer Token
    ]);
    curl_setopt($ch, CURLOPT_POST, true); // Método POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload); // Dados no corpo da requisição

    // Executa a requisição
    $response = curl_exec($ch);

    // Verifica se houve erro na requisição
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new \Exception("Erro ao comunicar com a API: " . $error);
    }

    // Fecha a sessão cURL
    curl_close($ch);

    // Decodifica a resposta JSON e retorna
    $responseData = json_decode($response, true);

    // Verifica se a resposta é válida
    if (isset($responseData['error'])) {
        throw new \Exception("Erro na resposta da API: " . $responseData['error']['message']);
    }

    return $responseData;
}


    /**
     * Lógica de tentativa e atraso em caso de erro.
     */
    private function retry($callback)
    {
        $retries = 0;
        do {
            try {
                return $callback();
            } catch (\Exception $e) {
                $retries++;
                if ($retries >= $this->maxRetries) {
                    throw $e;
                }
                sleep($this->retryDelay);
            }
        } while ($retries < $this->maxRetries);
    }

    /**
     * Codifica a imagem em Base64.
     */
    private function encodeImageToBase64($imagePath)
    {
        $img_path = \Yii::getAlias('@app') . "/prints/" . $imagePath;

        if (!file_exists( $img_path)) {
            return $img_path;
        }

        return base64_encode(file_get_contents($img_path));
    }
}
