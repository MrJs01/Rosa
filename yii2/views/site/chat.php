<?php

function add_memory($result)
{
    // salvar session
    $_SESSION['memory'][] = $result['memory'];

    return "Adicionei a Memória: " . json_encode($result['memory']);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if(isset($_POST['reset'])) {
        unset($_SESSION['memory']);
        echo json_encode(['response' => 'Memória resetada', 'history' => []]);
        exit;
    }



    $message = $_POST['message']; // Mensagem do usuário
    $history = json_decode($_POST['history'], true); // Histórico passado do JS
    $memory = (isset($_SESSION['memory'])) ? json_encode($_SESSION['memory']) : '';

    // Adicionar a nova mensagem no histórico
    $history[] = [
        'role' => 'user',
        'parts' => [['text' => <<<EOT
        <system> Você é o chatbot Rosa, desenvolvido pela empresa/equipe Crom. https://crom.live. Você têm o objetivo de ajudar o usuário a resolver seus problemas e conversar sobre a vida. Você é senior em algumas liguagens de programação e têm a capacidade de salvar memorias para uso futuro caso seja necessário. </system>

        <memory>
        {$memory}
        </memory>

        <prompt-user>
        $message
        </prompt-user>
        EOT]]
    ];

    if (strpos($message, '/memoria') !== false) {
        $message = substr($message, strpos($message, '/memoria') + 6);

        $message = add_memory(['memory' => $message]);


        echo json_encode(['response' => $message, 'history' => $history]);
        exit;
    }

    // Configuração da requisição cURL
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=" . (require __DIR__ . '/../../components/apikeyGemini.php')['key'];
    $data = [
        "contents" => $history,
        "generationConfig" => [
            "temperature" => 1,
            "topK" => 40,
            "topP" => 0.95,
            "maxOutputTokens" => 8192,
            "responseMimeType" => "text/plain"
        ],
        "tools" => [
            [
                "function_declarations" => [
                    [
                        "name" => "add_memory",
                        "description" => "Adicionar Memória do usuário. Adicione uma memória só quando usuario falar algo interessante",
                        "parameters" => [
                            "type" => "object",
                            "properties" => [
                                "memory" => [
                                    "type" => "string",
                                    "description" => "Memória do usuário"
                                ]
                            ],
                            "required" => []
                        ]
                    ]
                ]
            ]
        ]
    ];

    // Inicializar cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    // Enviar a requisição
    $response = curl_exec($ch);

    // Verificar se houve erro na requisição
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }

    curl_close($ch);

    // Decodificar a resposta da API
    $responseData = json_decode($response, true);


    // substituir a ultima mensagem do usuario pela mensagem "asd"
    $history[count($history) - 1]['parts'][0]['text'] = $message;



    // Exibir a resposta (exemplo de como exibir a primeira parte do conteúdo)
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        echo json_encode(['response' => $responseData['candidates'][0]['content']['parts'][0]['text'], 'history' => $history]);
    } else if (isset($responseData['candidates'][0]['content']['parts'][0]['functionCall'])) {
        $function_name = $responseData['candidates'][0]['content']['parts'][0]['functionCall']['name'];
        $function_args = $responseData['candidates'][0]['content']['parts'][0]['functionCall']['args'];

        // executar a função
        $response_text = $function_name($function_args);


        echo json_encode(['response' => $response_text, 'history' => $history]);
    } else {
        echo json_encode(['response' => "Erro ao obter resposta da API.", 'history' => $history, 'error' => true, 'error_message' => $response]);
    }

    exit;
}
?>

<style>
    body{
        background-image: url("https://crom.live/wp-content/uploads/2024/11/cropped-rosa-1-1-scaled-1.jpeg")  !important;
        background-size: cover;
        background-repeat: no-repeat;

    }

    .chat-area {
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        height: 80vh;
    }

    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
    }

    .message {
        padding: 10px;
        margin: 10px 0;
        border-radius: 15px;
        max-width: 75%;
    }

    .sent {
        background-color: #007bff;
        color: white;
        align-self: flex-end;
    }

    .received {
        background-color: #f1f1f1;
        color: black;
        align-self: flex-start;
    }

    .input-group {
        padding: 15px;
    }

    #sendMessage,
    #resetHistory {
        border-radius: 20px;
    }

    #messageInput {
        border-radius: 20px;
    }

    .chat-box {
        width: 100%;
        max-width: 800px;
        margin: 0 auto;
        border-radius: 20px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .bottom-menu {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 9999;
        background-color: rgba(0, 0, 0, 0.75);
        padding: 15px 0;
        text-align: center;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .bottom-menu button {
        padding: 10px 20px;
        font-size: 18px;
    }

    /* mensagem para cada lado */
    #chatMessages {
        display: flex;
        flex-direction: column;
    }

    #chatMessages .message {
        max-width: 80%;
        align-self: flex-end;
    }

    #chatMessages .received {
        max-width: 80%;

        align-self: flex-start;
    }
</style>

<div id="menu" class="bottom-menu">
    <button id="startChat" class="btn btn-primary">Iniciar Chat</button>
</div>

<div id="chatArea" class="chat-area" style="display: none;">
    <div class="chat-box">
        <div class="card-header">
            <h1 class="text-center text-white">Rosa</h1>
            <p class="text-center text-white">Chat da <a href="https://crom.live">Crom</a></p>
        </div>
        <div id="chatMessages" class="chat-messages">
            <!-- Mensagens do chat irão aparecer aqui -->
        </div>
        <div class="input-group gap-1 d-flex">
            <input id="messageInput" type="text" class="form-control" placeholder="Digite sua mensagem...">
            <button id="sendMessage" class="btn btn-primary">Enviar</button>
            <button id="resetHistory" class="btn btn-warning">Reiniciar Histórico</button> <!-- Botão para reiniciar -->
            <button id="resetMemory" class="btn btn-danger">Limpar Memória</button>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        let chatHistory = [{
                "role": "user",
                "parts": [{
                    "text": "eae\n"
                }]
            },
            {
                "role": "model",
                "parts": [{
                    "text": "Eae também! Tudo bem? (How's it going?)\n"
                }]
            }
        ]; // Histórico inicial

        // Iniciar chat
        $('#startChat').on('click', function() {
            $('#menu').fadeOut(500, function() {
                $('#chatArea').fadeIn(500);
            });
        });

        // Enviar mensagem
        $('#sendMessage').on('click', function() {
            let messageText = $('#messageInput').val();
            if (messageText.trim() !== "") {
                // Exibir mensagem do usuário
                $('#chatMessages').append('<div class="message sent">' + messageText + '</div>');
                $('#messageInput').val(''); // Limpar o campo de entrada

                // Adicionar a mensagem do usuário no histórico
                chatHistory.push({
                    "role": "user",
                    "parts": [{
                        "text": messageText
                    }]
                });

                // Enviar mensagem para o servidor (PHP)
                $.ajax({
                    url: '', // O mesmo arquivo PHP que fará a requisição cURL
                    method: 'POST',
                    data: {
                        message: messageText,
                        history: JSON.stringify(chatHistory), // Passar o histórico
                        _csrf: '<?php echo Yii::$app->request->getCsrfToken(); ?>'
                    },
                    dataType: 'json'
                }).done(function(response) {
                    console.log(response);
                    // Exibir resposta do servidor
                    $('#chatMessages').append('<div class="message received">' + response.response + '</div>');

                    // Atualizar o histórico com a resposta do modelo
                    chatHistory = response.history;
                    $('#chatMessages').scrollTop($('#chatMessages')[0].scrollHeight); // Rolagem automática
                }).fail(function(a, b, c) {
                    console.log(a, b, c);
                    $('#chatMessages').append('<div class="message received">Erro na comunicação com o servidor.</div>');
                });
            }
        });

        // Enviar mensagem ao pressionar Enter
        $('#messageInput').on('keypress', function(event) {
            if (event.which === 13) { // Enter
                $('#sendMessage').click();
            }
        });

        // Reiniciar histórico com confirmação
        $('#resetHistory').on('click', function() {
            if (confirm('Tem certeza que deseja reiniciar o histórico?')) {
                chatHistory = []; // Limpar o histórico
                $('#chatMessages').empty(); // Limpar as mensagens exibidas
            }
        });
        $('#resetMemory').on('click', function() {
            if (confirm('Tem certeza que deseja reiniciar a memória?')) {
                $.ajax({
                    url: '/chat', // O mesmo arquivo PHP que fará a requisição cURL
                    method: 'POST',
                    data: {
                        _csrf: '<?php echo Yii::$app->request->getCsrfToken(); ?>',
                        reset: true
                    },
                    dataType: 'json'
                }).done(function(response) {
                    console.log(response);
                }).fail(function(a, b, c) {
                    console.log(a, b, c);
                    $('#chatMessages').append('<div class="message received">Erro na comunicação com o servidor.</div>');
                });
            }
        });
    });
</script>