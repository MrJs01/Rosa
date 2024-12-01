<?php

class Agent
{
    private $name;
    public $task;
    private $apiKey;
    public $history;

    public function __construct($name, $task, $apiKey, $history)
    {
        $this->name = $name;
        $this->task = $task;
        $this->apiKey = $apiKey;
        $this->history = $history;
    }

    // Função que realiza a chamada à API Gemini
    public function execute()
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key={$this->apiKey}";
    
        $data = [
            "contents" => [
                [
                    "parts" => [
                        [
                            "text" => <<<EOT
    Historico:
    $this->history
    
    $this->task
    EOT
                        ]
                    ]
                ]
            ]
        ];
    
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    
        $response = curl_exec($ch);
    
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
    
        curl_close($ch);
    
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
        if ($httpCode !== 200) {
            throw new Exception('Erro ao chamar API');
        }
    
        // Retorna a resposta
        return json_decode($response, true);
    }

    public function getName()
    {
        return $this->name;
    }
}

class MetaAgent
{
    private $agents = [];
    private $apiKey;
    public $history = ''; // Histórico compartilhado

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function addAgent($agent)
    {
        $this->agents[] = $agent;
    }

    public function updateHistory($newEntry)
    {
        $this->history .= $newEntry . "\n\n";
    }

    public function executeTask($task, $numExecutions, $random_order = false)
    {
        $results = [];

        if($random_order) {
            $agents_random = $this->agents;
            // random
            shuffle($agents_random);

            
        }else{
            $agents_random = $this->agents;
        }

        foreach ($agents_random as $agent) {
            for ($i = 0; $i < $numExecutions; $i++) {
                // $agent->task = $task;

                // Adiciona o histórico compartilhado ao agente
                $agent->history = $this->history;

                $agentResult = $agent->execute();

                // Atualiza o histórico compartilhado
                $this->updateHistory($agentResult['candidates'][0]['content']['parts'][0]['text'] ?? 'Nenhuma resposta');

                $results[] = [
                    'agent' => $agent->getName(),
                    'result' => $agentResult
                ];
            }
        }

        return $results;
    }
}

// verificar se existe post
if (!empty($_POST)) {

    // // Define a chave da API
    // $apiKey = (require __DIR__ . '/../../components/apikeyGemini.php')['key'];

    // // Cria agentes com tarefas específicas
    // // Definindo os agentes para as tarefas específicas
    // $agent1 = new Agent('Agent1', 'Descrever problema', $apiKey);
    // $agent2 = new Agent('Agent2', 'Descrever solução sendo superada', $apiKey);
    // $agent3 = new Agent('Agent3', 'Descrever historia do heroi passando por um drama de vendas e superando', $apiKey);

    // // Cria o MetaAgent e adiciona os agentes
    // $metaAgent = new MetaAgent($apiKey);
    // $metaAgent->addAgent($agent1);
    // $metaAgent->addAgent($agent2);
    // $metaAgent->addAgent($agent3);

    // // Define a tarefa a ser executada
    // $task = "Gerar roteiro de filme sobre vendas e drama";

    // // Define quantas vezes cada agente deve executar a tarefa
    // $numExecutions = 2;

    // // Executa a tarefa com os agentes configurados
    // $results = $metaAgent->executeTask($task, $numExecutions);


    $agents = $_POST['agents_json'];
    $task = $_POST['task'];
    $numExecutions = $_POST['numExecutions'];
    $apiKey = (require __DIR__ . '/../../components/apikeyGemini.php')['key'];
    $metaAgent = new MetaAgent($apiKey);

    $random_order = isset($_POST['random_order']) ? true : false;

    $agents = json_decode($agents, true);

    $history = '';

    foreach ($agents as $agent) {
        $history_agent = $metaAgent->addAgent(new Agent($agent['name'], $agent['task'], $apiKey, $history));
    }



    $results = $metaAgent->executeTask($task, $numExecutions, $random_order);



?>

    <div class="container mt-4">
        <h2 class="mb-4">Results:</h2>
        <?php foreach ($results as $result): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <strong>Agent: <?php echo htmlspecialchars($result['agent']); ?></strong>
                </div>
                <div class="card-body">
                    <?php if (!empty($result['result'])): ?>
                        <?php
                        $content = $result['result']['candidates'][0]['content']['parts'][0]['text'] ?? 'No response';
                        ?>
                        <p class="card-text"><strong>Response:</strong></p>
                        <div class="alert alert-info">
                            <!-- markdown -->
                            <?= \yii\helpers\Markdown::process($content) ?>
                        </div>
                    <?php else: ?>
                        <p class="text-danger">No response content available.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>


<?php

}

?>

<!-- formulario para criar agente -->
<div class="container mt-4">
    <h2 class="mb-4">Create Agent:</h2>
    <div class="row">
        <div class="col-md-6">
            <form method="post" id="create-agent-form">
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="task">Task:</label>
                    <input type="text" class="form-control" id="task" name="task" required>
                </div>
                <button type="submit" class="btn btn-primary">Create Agent</button>
            </form>
        </div>
        <div class="col-md-6" id="agent-list"></div>
    </div>
</div>

<script>
    var agents = [];

    $(document).ready(function() {
        // criar agente na lista

        $('#create-agent-form').submit(function(e) {
            e.preventDefault();
            var name = $(this).find('#name').val();
            var task = $(this).find('#task').val();

            var agent = {
                name: name,
                task: task
            };

            agents.push(agent);

            // limpar formulario
            $(this).find('#name').val('');
            $(this).find('#task').val('');

            // atualizar lista de agentes
            var agentList = $('#agent-list');
            agentList.empty();

            for (var i = 0; i < agents.length; i++) {
                var agent = agents[i];
                agentList.append('<p>' + agent.name + ': ' + agent.task + ' <button class="btn btn-danger" onclick="removeAgent(' + i + ')">Remove</button></p>');
            }

            // atualizar formulario de tarefa

            var agentsInput = $('#execute-task-form').find('input[name="agents_json"]');
            agentsInput.val(JSON.stringify(agents));

        });

    })

    function removeAgent(index) {
        agents.splice(index, 1);
        var agentList = $('#agent-list');
        agentList.empty();

        for (var i = 0; i < agents.length; i++) {
            var agent = agents[i];
            agentList.append('<p>' + agent.name + ': ' + agent.task + ' <button class="btn btn-danger" onclick="removeAgent(' + i + ')">Remove</button></p>');
        }
    }
</script>

<!-- formalrio de tarefa e configuracoes -->

<div class="container mt-4">
    <h2 class="mb-4">Execute Task:</h2>
    <form method="post" action="/agent" id="execute-task-form">
        <input type="hidden" name="agents_json" value="">
        <input type="hidden" name="_csrf" value="<?= Yii::$app->request->getCsrfToken() ?>">

        <div class="form-group">
            <label for="task">Task:</label>
            <input type="text" class="form-control" id="task" name="task" required>
        </div>
        <div class="form-group">
            <label for="numExecutions">Number of Executions:</label>
            <input type="number" class="form-control" id="numExecutions" name="numExecutions" min="1" required>
        </div>
        <!-- checkbox random order -->
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="random_order" name="random_order">
            <label class="form-check-label" for="random_order">Random Order</label>
        </div>
        <button type="submit" class="btn btn-primary">Execute Task</button>
    </form>
</div>