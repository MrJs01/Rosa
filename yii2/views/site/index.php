<?php

use app\components\GeminiAgent;
use yii\helpers\Html;

/** @var yii\web\View $this */

$this->title = 'Print to Code gemini';

// verificar post
if (Yii::$app->request->isPost) {
    $image = $_FILES['image'];
    $image['base64'] = base64_encode(file_get_contents($image['tmp_name']));
    if (isset($image['type'], $image['base64'])) { // Verifica se as chaves 'type' e 'base64' estão presentes
        $image_name = uniqid() . '.' . explode('/', $image['type'])[1];
        $image_path = Yii::getAlias('@webroot') . '/prints/' . $image_name;
        file_put_contents($image_path, base64_decode($image['base64']));

        $prompt = Yii::$app->request->post('prompt');
        $geminiAgent = new GeminiAgent();
        $results = $geminiAgent->processImageAndPrompt($image_name, $prompt);

        // set result session
        $_SESSION['result'] = $results['agent2']['candidates'][0]['content']['parts'][0]['text'];
    } else {
        $results = []; // Caso a imagem ou suas chaves não existam
        echo "Erro ao enviar a imagem.";
        echo "<pre>";
        print_r($image);
        echo "</pre>";
    }
} else {
    $results = [];
}


?>
<div class="container mt-5 ">
    <!-- Título da página -->
    <div class="section-title">
        <h1 class="text-center"><?= Html::encode($this->title) ?></h1>
    </div>
    <!-- // formulario para enviar a imagem e o prompt -->
    <form action="/" method="post" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= Yii::$app->request->getCsrfToken() ?>">

        <div class="mb-3">
            <label for="image" class="form-label">Imagem:</label>
            <input type="file" name="image" id="image" class="form-control" accept="image/*" required>
            <div id="imageHelp" class="form-text">Escolha uma imagem para enviar.</div>
        </div>

        <div class="mb-3">
            <label for="prompt" class="form-label">Prompt:</label>
            <input type="text" name="prompt" id="prompt" class="form-control" placeholder="Digite o prompt" required>
            <div id="promptHelp" class="form-text">Insira uma descrição ou prompt para a imagem.</div>
        </div>


        <button type="submit" class="btn btn-primary w-100">Enviar</button>
    </form>


    <?php

    if (!empty($results)) {

    ?>





        <div class="sections-container">
            <!-- mostrar imagem enviada -->
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
                        <pre class="bg-light p-3" id="agent2-html"><?= Html::encode($results['agent2']['candidates'][0]['content']['parts'][0]['text']) ?></pre>
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
                        <div id="agent2-iframe"> <?= ($results['agent2']['candidates'][0]['content']['parts'][0]['text']) ?> </div>
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


        <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                document.getElementById("agent1-description").innerHTML = marked.parse(document.getElementById("agent1-description").innerHTML);
                document.getElementById("agent3-summary").innerHTML = marked.parse(document.getElementById("agent3-summary").innerHTML);


            });
        </script>
    <?php
    }
    ?>

</div>