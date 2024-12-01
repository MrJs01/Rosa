<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;

class SiteController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

    // result
    public function actionResult()
    {
        // definir sem layout padrao
        $this->layout = false;

        return $this->render('result');
    }

    public function actionCanvas()
    {
        $model = new \yii\base\DynamicModel(['text']);
        return $this->render('canvas', ['model' => $model]);
    }
    public function actionProcess()
    {
        $model = new \yii\base\DynamicModel(['text']);
        $model->load(Yii::$app->request->post());

        // Verificar se o campo de texto não está vazio
        if ($model->validate() && $model->text) {
            $response = $this->generateContentWithAI($model->text);
            return $this->render('canvas', [
                'model' => $model,
                'response' => $response
            ]);
        }

        // Caso o texto seja vazio ou tenha erro, voltar para a mesma página
        return $this->render('canvas', ['model' => $model]);
    }

    private function generateContentWithAI($text)
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . (require __DIR__ . '/../components/apikeyGemini.php')['apiKey'];

        $data = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $text]
                    ]
                ]
            ]
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            return 'Erro na requisição: ' . curl_error($ch);
        }

        curl_close($ch);

        // Suponha que a resposta seja um JSON e estamos interessados no campo de conteúdo gerado
        $responseData = json_decode($response, true);

        return $responseData['generatedContent'] ?? 'Nenhum conteúdo gerado.';
    }





    public function actionChat(){
        return $this->render('chat');
    }
    
    // agent
    public function actionAgent(){
        return $this->render('agent');
    }
}
