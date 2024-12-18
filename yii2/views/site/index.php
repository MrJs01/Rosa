<?php

use app\components\GeminiAgent;
use yii\helpers\Html;

/** @var yii\web\View $this */

$this->title = 'Print to Code gemini';

if (Yii::$app->request->isPost) {
    $image = $_FILES['image'] ?? null;
    if ($image) {
        $image['base64'] = base64_encode(file_get_contents($image['tmp_name']));
        $image_name = uniqid() . '.' . explode('/', $image['type'])[1];
        $image_path = Yii::getAlias('@webroot') . '/prints/' . $image_name;
        file_put_contents($image_path, base64_decode($image['base64']));

        $prompt = Yii::$app->request->post('prompt');
        $geminiAgent = new GeminiAgent();
        $results = $geminiAgent->processImageAndPrompt($image_name, $prompt);

        $_SESSION['result'] = $results['agent2']['candidates'][0]['content']['parts'][0]['text'];
    } else {
        echo "Erro ao enviar a imagem.";
    }
} else {
    $results = [];
}

?>
<div class="container mt-5">
    <div class="section-title">
        <h1 class="text-center">Instruções</h1>
    </div>
    <p class="text-center">Envie a imagem de um design de site que gostaria de converter para códigos HTML. Você pode usar um prompt para ajudar o agente a entender o que deseja. Use ctrl-v para colar a imagem ou usando o botão de upload para selecionar a imagem. Use a caixa de texto para inserir o prompt instruções mais detalhadas para o agente gerar o código.</p>
</div>

<div class="container mt-5">
    <div class="section-title">
        <h1 class="text-center"><?= Html::encode($this->title) ?></h1>
    </div>
    <form id="form" action="/" method="post" enctype="multipart/form-data" class="p-4 border rounded shadow-sm bg-dark">
        <input type="hidden" name="_csrf" value="<?= Yii::$app->request->getCsrfToken() ?>">

        <!-- Pré-visualização da imagem -->
        <div id="image-preview-container" class="mb-4 text-center" style="display: none;">
            <img id="image-preview" src="#" alt="Imagem Colada"
                style="max-width: 100%; height: 200px; object-fit: contain;"
                class="img-fluid border rounded">
        </div>



        <!-- Upload de imagem -->
        <input type="file" name="image" id="image" class="d-none" accept="image/*">

        <div class="input-group mb-3">
            <span class="input-group-text p-0" style="max-height: 40px;">
                <button type="button" id="upload-button" class="btn btn-outline-secondary w-100 h-100 border-0 rounded-0">
                    <i class="material-icons">upload</i>
                </button>
            </span>
            <div class="form-control border-0 bg-transparent text-white">
                <textarea name="prompt" id="prompt"
                    class="form-control shadow-sm"
                    placeholder="Digite ou cole o prompt aqui"
                    rows="4" required></textarea>
            </div>
            <span class="input-group-text p-0" style="max-height: 40px;">
                <button type="submit" class="btn btn-primary w-100 h-100 shadow-sm border-0" id="send-button">
                    <i class="material-icons">send</i>
                </button>
            </span>
        </div>

    </form>


    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const uploadButton = document.getElementById("upload-button");
            const imageInput = document.getElementById("image");
            const promptTextarea = document.getElementById("prompt");
            const imagePreviewContainer = document.getElementById("image-preview-container");
            const imagePreview = document.getElementById("image-preview");

            // Clique no botão para abrir o seletor de arquivo
            uploadButton.addEventListener("click", () => imageInput.click());

            // Atualizar pré-visualização quando o arquivo é selecionado manualmente
            imageInput.addEventListener("change", (event) => {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        imagePreview.src = e.target.result;
                        imagePreviewContainer.style.display = "block";
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Permitir colar imagens no textarea
            promptTextarea.addEventListener("paste", (event) => {
                const items = (event.clipboardData || event.originalEvent.clipboardData).items;
                for (let item of items) {
                    if (item.type.startsWith("image/")) {
                        const file = item.getAsFile();
                        const reader = new FileReader();

                        // Exibir a imagem colada na pré-visualização
                        reader.onload = (e) => {
                            imagePreview.src = e.target.result;
                            imagePreviewContainer.style.display = "block";
                        };
                        reader.readAsDataURL(file);

                        // Adicionar a imagem ao input file para envio
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(file);
                        imageInput.files = dataTransfer.files;

                        alert("Imagem adicionada ao formulário!");
                    }
                }
            });

            $("#send-button").click(function() {
                $("#form").find('textarea').trigger('disabled', true);
                $("#form").find('button').trigger('disabled', true);
                $("#send-button").attr('disabled', true);
                $("#upload-button").attr('disabled', true);

                $("#form").submit();
            });
        });
    </script>

    <?php if (!empty($results)) { ?>
        <div class="sections-container">
            <!-- exibição dos resultados -->
            <div class="section">
                <div class="card">
                    <div class="card-header">
                        <h5>Imagem Enviada</h5>
                    </div>
                    <div class="card-body">
                        <img src="/prints/<?= $image_name ?>" alt="Imagem Enviada" class="img-fluid">
                    </div>
                </div>
            </div>



            <!-- Agente 1: Descrição e Plano Detalhado -->
            <div class="section">
                <div class="card">
                    <div class="card-header">
                        <h5>Agente 1: Descrição e Plano Detalhado</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Descrição da Imagem:</strong></p>
                        <p id="agent1-description"><?= (Html::encode($results['agent1']['candidates'][0]['content']['parts'][0]['text'])) ?></p>
                    </div>
                </div>
            </div>

            <!-- Agente 2: Código HTML -->
            <div class="section">
                <div class="card">
                    <div class="card-header">
                        <h5>Agente 2: Código HTML</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>HTML Gerado (Bootstrap):</strong></p>
                        <pre class="bg-dark p-3" id="agent2-html"><?= Html::encode($results['agent2']['candidates'][0]['content']['parts'][0]['text']) ?></pre>
                    </div>
                </div>
            </div>

            <!-- Agente 3: Resumo Detalhado -->
            <div class="section">
                <div class="card">
                    <div class="card-header">
                        <h5>Agente 3: Resumo Detalhado</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Resumo das Ações:</strong></p>
                        <p id="agent3-summary"><?= nl2br(Html::encode($results['agent3']['candidates'][0]['content']['parts'][0]['text'])) ?></p>
                    </div>
                </div>
            </div>


            <!-- Última Seção com o iframe do Agente 2 -->
            <div class="section" id="iframe-section">
                <div class="card">
                    <div class="card-header">
                        <h5>Agente 2: Resultado no iframe</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Exibição do HTML no iframe:</strong></p>
                        <iframe src="/result?return=false" id="agent2-iframe" frameborder="0"></iframe>
                        <!-- result -->
                        <a class="btn btn-primary" href="/result">Ver Resultado</a>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .sections-container {
                display: flex;
                flex-direction: column;
                gap: 20px;
                width: 100%;
            }

            .section {
                width: 100%;
            }

            .section-title {
                margin-bottom: 30px;
            }

            .card {
                margin-bottom: 20px;
            }

            #agent2-iframe {
                width: 100%;
                height: 500px;
                border: 1px solid #ccc;
                position: relative;
                overflow: scroll;
            }
        </style>


        <script type="module">
            import {
                marked
            } from "https://cdn.jsdelivr.net/npm/marked/lib/marked.esm.js";
            document.addEventListener("DOMContentLoaded", function() {
                document.getElementById("agent1-description").innerHTML = marked.parse(document.getElementById("agent1-description").innerHTML);
                document.getElementById("agent3-summary").innerHTML = marked.parse(document.getElementById("agent3-summary").innerHTML);


            });
        </script>
    <?php
    }
    ?>

</div>