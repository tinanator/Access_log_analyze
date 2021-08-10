# Access_log_analyze

<h1>Требования:</h1>

<ul>
<li>PHP ^7.4</li>
<li>Composer</li>
<li>PHPUnit ^9.5 </li>
</ul>

<h1>Инструкция по установке:</h1>

Для запуска тестов используется PHPUnit

`composer require --dev phpunit/phpunit`

<h1>Инструкция по запуску:</h2>

`cat access.log | php analyze.php -u 99.9 -t 45`

<p>где access.log - файл с логами, который передается скрипту,</p> 
<p>analyze.php - файл со скриптом,</p> 
<p>флаг -u - минимально допустимый уровень доступности в процентах, например, 99.9</p> 
<p>флаг -t - приемлемое время ответа, например, 45</p>



