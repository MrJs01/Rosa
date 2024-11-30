<?php

echo $_SESSION['result'] ? $_SESSION['result'] : 'Fail <a href="/>Try again</a>';

?>

<a href="/" id="try-again">Try again</a>
<style>
    #try-again {
        position: fixed;
        right: 10px;
        bottom: 10px;
        text-decoration: none;
        color: #fff;
        background: #000;
        padding: 5px 10px;
        border-radius: 5px;
    }
</style>