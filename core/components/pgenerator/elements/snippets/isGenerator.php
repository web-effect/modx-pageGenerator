<?php
$modx->getService('pgenerator','PGenerator',MODX_CORE_PATH.'components/pgenerator/model/pgenerator/');
return $modx->pgenerator->isGenerator((int)$input)?'1':'0';