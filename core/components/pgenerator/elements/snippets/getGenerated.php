<?php
$modx->getService('pgenerator','PGenerator',MODX_CORE_PATH.'components/pgenerator/model/pgenerator/');
return $modx->pgenerator->getGeneratorGeneratedInRoot((int)$input,(int)$options);