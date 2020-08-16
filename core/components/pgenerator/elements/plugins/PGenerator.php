<?php
$corePath = $modx->getOption('pgenerator.core_path', null, $modx->getOption('core_path', null, MODX_CORE_PATH) . 'components/pgenerator/');
$component = $modx->getService(
    'pgenerator',
    'PGenerator',
    $corePath . 'model/pgenerator/',
    array(
        'core_path' => $corePath
    )
);

if (!($component instanceof PGenerator)) return '';

$className = "\\PGenerator\\Events\\{$modx->event->name}";
if (class_exists($className)) {
    /** @var \PGenerator\Events\Event $handler */
    $handler = new $className($modx, $scriptProperties);
    $handler->run();
}

return;