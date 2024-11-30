<?php
use app\components\GeminiAgent;

/** @var yii\web\View $this */

$this->title = 'My Yii Application';


$geminiAgent = new GeminiAgent();
$results = $geminiAgent->processImageAndPrompt('/prints/1.png', 'Descreva a imagem e crie um plano detalhado.');

// echo "Agente 1 Resultado: " . $results['agent1']['text'];
// echo "Agente 2 Resultado (HTML): " . $results['agent2']['text'];
// echo "Agente 3 Resumo: " . $results['agent3']['text'];




?>
