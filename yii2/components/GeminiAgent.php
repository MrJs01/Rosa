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

    // quando iniciar o componente
    public function init()
    {
        $this->apiKey = (require __DIR__ . '/apikeyGemini.php')['key'];
        // $this->apiKey['key'] = "API_KEY";
        parent::init();
    }

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
                        ["text" => "Descreva a imagem e crie um plano detalhado. Faça um plano detalhado para criar essa pagina usando bootstrap. Siga instruções do usuario tambem se necessário:" . $prompt],
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

        $result = $this->sendRequest("gemini-1.5-flash:generateContent", $payload);
        if ($result['candidates'][0]['content']['parts'][0]['text'] == null) {
            // refazer
            return $this->agent1($encodedImage, $prompt);
        }

        return $result;
    }

    /**
     * Agente 2: Geração da página em HTML usando Bootstrap.
     */
    private function agent2($agent1Result)
    {

        $text = $agent1Result['candidates'][0]['content']['parts'][0]['text'];

        $prompt = "Retorne apenas o html. Não acrescente ```html e nenhum outro texto que não seja o html.Crie uma página completa em HTML usando Bootstrap com base neste plano: " . $text;
        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt],
                    ]
                ]
            ]
        ];

        $result = $this->sendRequest("gemini-1.5-flash:generateContent", $payload);
        if ($result['candidates'][0]['content']['parts'][0]['text'] == null) {
            // refazer
            return $this->agent2($agent1Result);
        }

        return $result;
    }

    /**
     * Agente 3: Resumo detalhado.
     */
    private function agent3($agent1Result, $agent2Result)
    {
        $text1 = $agent1Result['candidates'][0]['content']['parts'][0]['text'];
        $text2 = $agent2Result['candidates'][0]['content']['parts'][0]['text'];
        $prompt = "Resuma detalhadamente o que foi feito com base nos seguintes resultados:\n\n" .
            "Descrição e plano do agente 1: " . $text1 . "\n\n" .
            "Código HTML gerado pelo agente 2: " . $text2;

        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt],
                    ]
                ]
            ]
        ];

        $result = $this->sendRequest("gemini-1.5-flash:generateContent", $payload);
        if ($result['candidates'][0]['content']['parts'][0]['text'] == null) {
            // refazer
            return $this->agent3($agent1Result, $agent2Result);
        }

        return $result;
    }

    /**
     * Envia uma requisição para a API do Gemini.
     */
    private function sendRequest($endpoint, $payload)
    {
        // URL base da API
        $url = $this->apiUrl . $endpoint . "?key=" . $this->apiKey;

        // Converte o payload para JSON
        $jsonPayload = json_encode($payload);

        // Inicializa o cURL
        $ch = curl_init($url);

        // Configurações do cURL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Retorna o resultado como string
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json', // Tipo de conteúdo
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
        // web/prints/
        $img_path = Yii::getAlias('@webroot') . '/prints/' . $imagePath;

        if (!file_exists($img_path)) {
            return false;
        }

        return base64_encode(file_get_contents($img_path));
    }
}
