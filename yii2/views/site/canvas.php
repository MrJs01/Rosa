<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = 'Open Canvas';
?>
<div class="canvas-container">
    <h1>Canvas - Geração de Conteúdo com AI</h1>

    <div class="form-container">
        <?php $form = ActiveForm::begin([
            'action' => ['canvas/process'],
            'method' => 'post',
            'options' => ['class' => 'canvas-form'],
        ]); ?>

        <?= $form->field($model, 'text')->textarea(['rows' => 6, 'placeholder' => 'Digite o texto para gerar conteúdo...'])->label('Conteúdo') ?>

        <div class="form-group">
            <?= Html::submitButton('Gerar Conteúdo', ['class' => 'btn btn-primary']) ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>

    <?php if (isset($response)): ?>
        <div class="response-container">
            <h3>Conteúdo Gerado</h3>
            <p><?= Html::encode($response) ?></p>
        </div>
    <?php endif; ?>
</div>

<style>
    .canvas-container {
        width: 100%;
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }
    .form-container {
        margin-bottom: 20px;
    }
    .response-container {
        background: #f8f8f8;
        padding: 20px;
        border: 1px solid #ddd;
    }
</style>
