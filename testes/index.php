<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reconhecimento de Voz e Síntese</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #121212;
            color: white;
            font-family: Arial, sans-serif;
        }

        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        #transcript,
        #history {
            width: 100%;
            height: 100px;
            background-color: #333;
            color: white;
            border: none;
            padding: 10px;
            resize: none;
        }

        .btn-custom {
            background-color: #007bff;
            color: white;
        }

        #history {
            margin-top: 20px;
            height: 200px;
            overflow-y: auto;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="text-center">
            <h1>Reconhecimento de Voz e Síntese</h1>
            <textarea id="transcript" readonly placeholder="Transcrição de voz aparecerá aqui..."></textarea><br>
            <button id="start-stop-btn" class="btn btn-custom mt-3">Iniciar Reconhecimento</button><br>
            <textarea id="text-to-speak" class="mt-3" placeholder="Texto gerado aparecerá aqui..." readonly></textarea><br>
            <textarea id="history" readonly placeholder="Histórico de Conversas..."></textarea>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4=" crossorigin="anonymous"></script>

    <script>
        class VoiceRecognition {
            constructor(buttonId, transcriptAreaId, model) {
                if ('webkitSpeechRecognition' in window) {
                    this.recognition = new webkitSpeechRecognition();
                    this.recognition.continuous = true;
                    this.recognition.interimResults = true;
                    this.recognition.lang = 'pt-BR';
                    this.model = model;

                    this.button = document.getElementById(buttonId);
                    this.transcriptArea = document.getElementById(transcriptAreaId);
                    this.isRecognizing = false;

                    this.button.addEventListener('click', () => this.toggleRecognition());

                    this.recognition.onresult = (event) => this.updateTranscript(event);
                    this.recognition.onerror = (event) => console.error('Erro no reconhecimento de voz:', event.error);

                    // Variável para controlar o tempo de silêncio
                    this.silenceTimeout = null;
                    this.silenceDelay = 1500; // 1.5 segundos de silêncio para finalizar a transcrição
                } else {
                    alert("API de reconhecimento de voz não é suportada neste navegador.");
                }
            }

            toggleRecognition() {
                if (this.isRecognizing) {
                    this.stop();
                } else {
                    this.start();
                }
            }

            start() {
                this.recognition.start();
                this.button.textContent = 'Pausar Reconhecimento';
                this.isRecognizing = true;
                this.transcriptArea.value = '';
            }

            stop() {
                this.recognition.stop();
                this.button.textContent = 'Iniciar Reconhecimento';
                this.isRecognizing = false;
            }

            async updateTranscript(event) {
                let transcript = '';
                let isFinal = false;

                for (let i = 0; i < event.results.length; i++) {
                    const result = event.results[i];
                    transcript += result[0].transcript;

                    // Detecta se a transcrição está finalizada
                    if (result.isFinal) {
                        isFinal = true;
                    }
                }

                let lines = transcript.split('\n');
                if (lines[lines.length - 1] !== '') {
                    speechSynthesis.cancel(); // Cancela qualquer fala em andamento
                }


                // Atualiza a área de transcrição em tempo real
                this.transcriptArea.value = transcript;

                // Se a transcrição estiver finalizada (usuário parou de falar), aguarda o delay
                if (isFinal) {
                    clearTimeout(this.silenceTimeout); // Limpa o timeout anterior

                    // Define um novo timeout que executa a função após o tempo de silêncio
                    this.silenceTimeout = setTimeout(async () => {
                        if (transcript.trim() !== '') {
                            // Chama o modelo para gerar uma resposta
                            const response = await this.model.generateResponse(transcript);
                            document.getElementById("text-to-speak").value = response;
                            new VoiceSynthesis('text-to-speak').speak();
                        }
                    }, this.silenceDelay);
                    transcript += '\n';
                }






            }
        }


        class VoiceSynthesis {
            constructor(textAreaId) {
                this.textArea = document.getElementById(textAreaId);
                this.utterance = null;
            }

            speak() {
                const textToSpeak = this.textArea.value;
                if (textToSpeak) {
                    if (this.utterance) {
                        speechSynthesis.cancel(); // Interrompe qualquer fala anterior
                    }
                    this.utterance = new SpeechSynthesisUtterance(textToSpeak);
                    this.utterance.lang = 'pt-BR';
                    this.utterance.rate = 2;
                    speechSynthesis.speak(this.utterance);
                } else {
                    alert("Texto vazio para falar.");
                }
            }
        }

        // Classe que interage com o modelo Gemini
        class GeminiModel {
            constructor(apiKey) {
                this.apiKey = apiKey;
                this.history = []; // Armazenar o histórico de conversas
                this.lastUserInput = ''; // Armazenar a última entrada do usuário
            }

            generateResponse(prompt) {
                // Ignorar entradas repetitivas
                if (prompt === this.lastUserInput) {
                    return Promise.resolve('Já falamos sobre isso.'); // Mensagem para entradas repetitivas
                }

                this.lastUserInput = prompt; // Atualiza a última entrada do usuário

                // Adiciona a entrada ao histórico
                this.history.push({
                    role: "user",
                    text: prompt
                });

                return $.ajax({
                    url: `https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=${this.apiKey}`,
                    type: "POST",
                    contentType: "application/json",
                    data: JSON.stringify({
                        contents: [{
                            role: "user",
                            parts: [{
                                text: prompt
                            }]
                        }],
                        generationConfig: {
                            temperature: 1,
                            topK: 40,
                            topP: 0.95,
                            maxOutputTokens: 8192,
                            responseMimeType: "text/plain"
                        }
                    }),
                }).then(response => {
                    const modelResponse = response.candidates[0].content.parts[0].text;

                    // Adiciona a resposta do modelo ao histórico
                    this.history.push({
                        role: "assistant",
                        text: modelResponse
                    });

                    // Atualiza o histórico na interface
                    document.getElementById("history").value = this.getHistory();

                    return modelResponse; // Retorna a resposta gerada
                }).catch(error => {
                    console.error("Erro ao chamar o modelo Gemini:", error);
                    return "Erro ao gerar resposta.";
                });
            }

            // Função para obter o histórico de conversas
            getHistory() {
                return this.history.map(entry => `${entry.role}: ${entry.text}`).join('\n');
            }

            // Função para limpar o histórico
            clearHistory() {
                this.history = [];
                document.getElementById("history").value = ''; // Limpa o histórico na interface
            }
        }

        // Inicializa a página
        document.addEventListener("DOMContentLoaded", () => {
            const apiKey = ""; // Substitua pela sua chave API
            const geminiModel = new GeminiModel(apiKey);
            new VoiceRecognition('start-stop-btn', 'transcript', geminiModel);
        });
    </script>
</body>

</html>