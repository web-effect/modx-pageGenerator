<?php

class PGenerator
{
    const NAMESPACE='pgenerator';
    public $modx;
    public $authenticated = false;
    public $errors = array();
    public $debug = 1;
	public $__cache = array();

    function __construct(modX &$modx, array $config = array())
    {
        $this->modx =& $modx;
        
        $localPath='components/'.static::NAMESPACE.'/';
        $corePath = $this->modx->getOption(static::NAMESPACE.'.core_path', $config, $this->modx->getOption('core_path') . $localPath);
        $assetsPath = $this->modx->getOption(static::NAMESPACE.'.assets_path', $config, $this->modx->getOption('assets_path') . $localPath);
        $assetsUrl = $this->modx->getOption(static::NAMESPACE.'.assets_url', $config, $this->modx->getOption('assets_url') . $localPath);
        $connectorUrl = $assetsUrl . 'connector.php';
        $context_path = $this->modx->context->get('key')=='mgr'?'mgr':'web';

        $this->config = array_merge(array(
            'assetsUrl' => $assetsUrl,
            'cssUrl' => $assetsUrl . $context_path . '/css/',
            'jsUrl' => $assetsUrl . $context_path . '/js/',
            'jsPath' => $assetsPath . $context_path . '/js/',
            'imagesUrl' => $assetsUrl . $context_path . '/img/',
            'connectorUrl' => $connectorUrl,
            'corePath' => $corePath,
            'modelPath' => $corePath . 'model/',
            'servicePath' => $corePath . 'model/'.static::NAMESPACE.'/',
            'chunksPath' => $corePath . 'elements/chunks/',
            'templatesPath' => $corePath . 'elements/templates/',
            'chunkSuffix' => '.chunk.tpl',
            'snippetsPath' => $corePath . 'elements/snippets/',
            'processorsPath' => $corePath . 'processors/',
        ), $config);

        $this->modx->lexicon->load(static::NAMESPACE.':default');
        $this->authenticated = $this->modx->user->isAuthenticated($this->modx->context->get('key'));
        $this->loadModel();
        
        spl_autoload_register(array($this,'autoload'));
    }

    public function initialize($scriptProperties = array(),$ctx = 'web')
    {
        $this->config['options'] = $scriptProperties;
        $this->config['ctx'] = $ctx;
        return true;
    }
    
    public function log($msg){
		if($this->debug)$this->modx->log(MODX_LOG_LEVEL_ERROR,$msg);
	}
    
    public function autoload($class){
        $class = explode('/',str_replace("\\", "/", $class));
        $className = array_pop($class);
        $classPath = strtolower(implode('/',$class));
        
        $path = $this->config['modelPath'].'/'.$classPath.'/'.$className.'.php';
        if(!file_exists($path))return false;
        include $path;
    }
    
    public function loadAssets($ctx){
        if(!$this->modx->controller)return false;
        $this->modx->controller->addLexiconTopic(static::NAMESPACE.':default');
        switch($ctx){
            case 'mgr':{
                $this->modx->controller->addJavascript($this->config['assetsUrl'].'mgr/js/'.static::NAMESPACE.'.js');
            }
        }
    }
    
    public function loadModel(){
        //Ищем файл metadata
        $metadata=$this->config['servicePath']."metadata.".$this->modx->config['dbtype'].'.php';
        if(file_exists($metadata))$this->modx->addPackage(static::NAMESPACE, $this->config['modelPath']);
    }
    
    public function getIDArrayFromString($str){
    	$ids=array();
		$ids=array_map('intval',preg_split('/,\s*/ui',$str,0,PREG_SPLIT_NO_EMPTY));
		return $ids;
    }
    
    public function extractPlaceholders($fields=array(),$prefix=''){
		$placeholders=array();
		foreach($fields as $placeholder=>$field){
			$placeholder=str_replace('.','_',$placeholder);
			$placeholder=$prefix.$placeholder;
			$placeholders[$placeholder]=$field;
		}
		return $placeholders;
	}
	public function processText($input,$placeholders=array()){
		$chunk = $this->modx->getObject('modChunk',array('name'=>$input));
		if(!$chunk)
		{
			$uniqid = uniqid();
			$chunk = $this->modx->newObject('modChunk', array('name' => "{tmp}-{$uniqid}"));
			$chunk->setCacheable(false);
		}
		else
		{
			$chunk->setCacheable(false);
			$input = $chunk->getContent();
		}

		$this->modx->getParser();
		if($this->modx->parser instanceof pdoParser)$output = $this->modx->parser->pdoTools->getChunk('@INLINE '.$input, $placeholders);
		else $output = $chunk->process($placeholders,$input);
		$maxIterations = (integer) $this->modx->getOption('parser_max_iterations', null, 10);
		$this->modx->parser->processElementTags('', $output, false, false, '[[', ']]', array(), $maxIterations);
		$this->modx->parser->processElementTags('', $output, true, true, '[[', ']]', array(), $maxIterations);
		return $output;
	}
    
    
    
	public function getParentsIds($id,$include=false){
		//!!!!!!!!!!!!!Кэшироввание!!!!!
		$parents=array();
		$q=$this->modx->newQuery('modResource',null,false);
		$q->innerJoin('modResource','l0','modResource.id=l0.id AND l0.id='.$id);
		$iterations=$this->modx->getOption('pgenerator.join_iterations',null,10,true);
		$selects=array();
		if($include)$selects[]='l0.id';
		for($i=1;$i<=$iterations;$i++){
			$q->leftJoin('modResource','l'.$i,'l'.$i.'.id=l'.($i-1).'.parent');
			$selects[]='l'.$i.'.id';
		}
		$q->select($selects);
		$q->prepare();
		//$this->log($q->toSQL());
		$q->stmt->execute();
		$parents=array_map('intval',array_diff($q->stmt->fetch(PDO::FETCH_NUM)?:array(),array(null)));
		$parents[]=0;
		//$this->log(json_encode($parents));
		
		return $parents;
	}
	public function getChildsIds($id,$include=false,$checkTV=false){
		//!!!!!!!!!!!!!Кэшироввание!!!!!
		$templates=false;
		if($checkTV){
			$q=$this->modx->newQuery('modTemplateVarTemplate');
			$q->innerJoin('modTemplateVar','tv','tv.id=modTemplateVarTemplate.tmplvarid AND tv.name="'.$checkTV.'"');
			$q->select(array('modTemplateVarTemplate.templateid'));
			$q->prepare();
			$q->stmt->execute();
			$templates = $q->stmt->fetchAll(PDO::FETCH_COLUMN)?:false;
			//$this->log(json_encode($templates));
		}
		$childs=array();
		if($include)$childs[]=$id;
		$q=$this->modx->newQuery('modResource',null,false);
		$join='modResource.id=l0.id AND l0.parent='.$id;
		if($templates)$join.=' AND l0.template IN ('.implode(',',$templates).')';
		$q->innerJoin('modResource','l0',$join);
		$iterations=$this->modx->getOption('pgenerator.join_iterations',null,10,true);
		$selects=array();
		$selects[]='l0.id';
		for($i=1;$i<=$iterations;$i++){
			$join='l'.$i.'.parent=l'.($i-1).'.id';
			if($templates)$join.=' AND l'.$i.'.template IN ('.implode(',',$templates).')';
			$q->leftJoin('modResource','l'.$i,$join);
			$selects[]='l'.$i.'.id';
		}
		$q->select($selects);
		$q->prepare();
		//$this->log($q->toSQL());
		$q->stmt->execute();
		while($row = $q->stmt->fetch(PDO::FETCH_NUM)){
			$_childs=array_map('intval',array_diff($row?:array(),array(null)));
			foreach($_childs as $_id){
				if(in_array($_id,$childs))continue;
				$childs[]=$_id;
			}
		}
		
		return $childs;
	}
	public function getResourcesTVValues($ids=array(),$prefix=false){
		$ids=array_map('intval',array_diff($ids,array(0,null)));
		$tvrs=array();
		$_ids=array();
		foreach($ids as $id){
			if(isset($this->__cache['TemaplteVarValues'][$id])){
				$tvrs[$id]=$this->__cache['TemaplteVarValues'][$id];
			}else{
				$_ids[]=$id;
			}
		}
		
		if(!empty($_ids)){
			$tvr_q=$this->modx->newQuery('modResource',null,false);
			$tvr_q->innerJoin('modResource','res','modResource.id=res.id AND res.id IN ('.implode(',',$_ids).')');
			$tvr_q->leftJoin('modTemplateVarTemplate','tvt','res.template=tvt.templateid');
			$tvr_q->leftJoin('modTemplateVar','tv','tv.id=tvt.tmplvarid');
			$tvr_q->leftJoin('modTemplateVarResource','tvr','tvr.contentid=res.id AND tvr.tmplvarid=tvt.tmplvarid');
			$tv_select = $this->modx->getSelectColumns('modTemplateVar','tv','tv_');
			$tvr_q->select(array(
				'res.id as tvr_resource',
				'IFNULL(tvr.value,tv.default_text) as tvr_value',
				'tv.name as tv_name',
				$tv_select
			));
			$tvr_q->prepare();
			//$this->log($tvr_q->toSQL());
			$tvr_q->stmt->execute();
			while($_tvr = $tvr_q->stmt->fetch(PDO::FETCH_ASSOC)){
				if(!isset($this->__cache['TemaplteVarValues'][$_tvr['tvr_resource']]))$this->__cache['TemaplteVarValues'][$_tvr['tvr_resource']]=array();
				$this->__cache['TemaplteVarValues'][$_tvr['tvr_resource']][$_tvr['tv_name']]=$_tvr['tvr_value'];
				if(!isset($this->__cache['TemaplteVar'][$_tvr['tv_id']]))$this->__cache['TemaplteVar'][$_tvr['tv_id']]=array();
				foreach($_tvr as $_tvr_field=>$_tvr_val){
					if(strpos($_tvr_field,'tv_')!==0)continue;
					$this->__cache['TemaplteVar'][$_tvr['tv_id']][substr($_tvr_field,3)]=$_tvr_val;
				}
			}
			
			foreach($_ids as $_id){
				$tvrs[$_id]=$this->__cache['TemaplteVarValues'][$_id];
			}
		}
		
		if($prefix){
			foreach($tvrs as $id=>$tvr){
				$keys=array_keys($tvr);
				array_walk($keys,function(&$v,$k,$prefix){ $v=$prefix.$v;},$prefix);
				$tvrs[$id]=array_combine($keys,array_values($tvr));
			}
		}
		
		
		return $tvrs;
	}
	public function getTemplates($ids=array()){
		$ids=array_map('intval',array_diff($ids,array(0,null)));
		$tpls=array();
		$_ids=array();
		foreach($ids as $id){
			if(isset($this->__cache['Temapltes'][$id])){
				$tpls[$id]=$this->__cache['Temapltes'][$id];
			}else{
				$_ids[]=$id;
			}
		}
		
		if(!empty($_ids)){
			$tpls_q=$this->modx->newQuery('modTemplate',null,false);
			$tpls_q->innerJoin('modTemplate','tpl','modTemplate.id=tpl.id AND tpl.id IN ('.implode(',',$_ids).')');
			$tpls_q->leftJoin('modTemplateVarTemplate','tvt','tpl.id=tvt.templateid');
			$tpls_q->leftJoin('modTemplateVar','tv','tv.id=tvt.tmplvarid');
			$tpl_select = $this->modx->getSelectColumns('modTemplate','tpl','tpl_');
			$tv_select = $this->modx->getSelectColumns('modTemplateVar','tv','tv_');
			$tpls_q->select(array(
				$tpl_select,
				$tv_select
			));
			$tpls_q->prepare();
			//$this->log($tpls_q->toSQL());
			$tpls_q->stmt->execute();
			while($_tpl = $tpls_q->stmt->fetch(PDO::FETCH_ASSOC)){
				if(!isset($this->__cache['Temapltes'][$_tpl['tpl_id']]))$this->__cache['Temapltes'][$_tpl['tpl_id']]=array('tvs'=>array());
				if(!isset($this->__cache['TemaplteVar'][$_tpl['tv_id']]))$this->__cache['TemaplteVar'][$_tpl['tv_id']]=array();
				foreach($_tpl as $_tpl_col=>$_tpl_val){
					if(strpos($_tpl_col,'tpl_')===0){
						$this->__cache['Temapltes'][$_tpl['tpl_id']][substr($_tpl_col,4)]=$_tpl_val;
					}
					if(strpos($_tpl_col,'tv_')===0){
						$this->__cache['Temapltes'][$_tpl['tpl_id']]['tvs'][$_tpl['tv_id']][substr($_tpl_col,3)]=$_tpl_val;
						$this->__cache['TemaplteVar'][$_tpl['tv_id']][substr($_tpl_col,3)]=$_tpl_val;
					}
				}
			}
			foreach($_ids as $_id){
				$tpls[$_id]=$this->__cache['Temapltes'][$_id];
			}
		}
		
		return $tpls;
	}
	public function getTVs($ids=array()){
		$ids=array_map('intval',array_diff($ids,array(0,null)));
		$tvs=array();
		$_ids=array();
		foreach($ids as $id){
			if(isset($this->__cache['TemaplteVar'][$id])){
				$tvs[$id]=$this->__cache['TemaplteVar'][$id];
			}else{
				$_ids[]=$id;
			}
		}
		
		if(!empty($_ids)){
			$tvs_q=$this->modx->newQuery('modTemplateVar',array('id:IN'=>$_ids),false);
			$tvs_q->prepare();
			//$this->log($tvs_q->toSQL());
			$tvs_q->stmt->execute();
			while($_tv = $tvs_q->stmt->fetch(PDO::FETCH_ASSOC)){
				$this->__cache['TemaplteVar'][$_tv['id']]=$_tv;
				$tvs[$_tv['id']]=$_tv;
			}
		}
		
		return $tvs;
	}
	
	
	
	public function getGeneratedRootsParents(){
		return $this->getIDArrayFromString($this->modx->getOption('pgenerator.generated.roots'));
	}
	public function getGeneratorsRoots(){
		return $this->getIDArrayFromString($this->modx->getOption('pgenerator.generator.roots'));
	}
	//Проверяет что ресуср является генератором
	public function isGenerator($id,$is_parent=false){
		$root = $this->getGeneratorsRoot($id);
		if(!$root)return false;
		$q=$this->modx->newQuery('modTemplateVarTemplate');
		$q->innerJoin('modTemplateVar','tv','modTemplateVarTemplate.tmplvarid=tv.id AND tv.name="generator_options"');
		$q->innerJoin('modResource','r','r.template=modTemplateVarTemplate.templateid AND r.id='.$id);
		$q->select(array('tmplvarid'));
		$q->prepare();
		//$this->log($q->toSQL());
		$q->stmt->execute();
		$r = $q->stmt->fetch(PDO::FETCH_ASSOC);
		return (bool)$r;
	}
	
	//Получение корня гераторов ресурсов для ресурса по его родителям
	public function getGeneratorsRoot($id){
		$roots = $this->getGeneratorsRoots();
		$parents = $this->getParentsIds($id,$is_parent);
		$root = current(array_intersect($roots,$parents));
		//$this->log($root.' -- '.json_encode($parents).' -- '.(int)in_array($root,$parents));
		return $root;
	}
	
	//Проверяет что ресуср является герерируемым
	public function isGenerated($id,$is_parent=false){
		$roots = $this->getGeneratedRootsParents();
		$parents = $this->getParentsIds($id,$is_parent);
		$roots_parent = current(array_intersect($roots,$parents));
		if(!$roots_parent)return false;
		$idx = array_search($roots_parent,$parents);
		if($idx===false||$idx===0)return false;
		return true;
	}
	
	//Проверяет что ресуср является корнем герерируемых ресурсов
	public function isGeneratedRoot($id,$is_parent=false){
		$roots = $this->getGeneratedRootsParents();
		$parents = $this->getParentsIds($id,$is_parent);
		$roots_parent = current(array_intersect($roots,$parents));
		if(!$roots_parent)return false;
		$idx = array_search($roots_parent,$parents);
		if($idx===false)return false;
		if($idx===0)return true;
		return false;
	}
	
	//Получение главного корня генерируемых ресурсов для ресурса по его родителям
	public function getGeneratedRootsParent($id){
		$roots = $this->getGeneratedRootsParents();
		$parents = $this->getParentsIds($id,$is_parent);
		$roots_parent = current(array_intersect($roots,$parents));
		//$this->log($root.' -- '.json_encode($parents).' -- '.(int)in_array($root,$parents));
		return $roots_parent;
	}
	
	
	
	
	//Получение главного корня генерируемых ресурсов для генератора
	public function getGeneratedRootsParentByGenerator($generator_id){
		$root = $this->getGeneratorsRoot($generator_id);
		if(!$root)return false;
		$gor_roots = $this->getGeneratorsRoots();
		$ged_roots_parents = $this->getGeneratedRootsParents();
		$roots_parent=$ged_roots_parents[array_search($root,$gor_roots)];
		return $roots_parent;
	}
	
	//Получение корня генераторов по главному корню генерируемых
	public function getGeneratorsRootByGeneratedRootsParent($id){
		$gor_roots = $this->getGeneratorsRoots();
		$ged_roots_parents = $this->getGeneratedRootsParents();
		$root=$gor_roots[array_search($id,$ged_roots_parents)];
		return $root;
	}
	
	//Получение итератора по корням генерируемых ресурсов для генератора
	public function getGeneratedRootsIterator($generator_id){
		$rootsParent = $this->getGeneratedRootsParentByGenerator($generator_id);
		return $this->modx->getIterator('modResource',array('parent'=>$rootsParent),false);
	}
	
	//Получение итератора по генераторам по корню геренируемых
	public function getGeneratorsIteratorByGeneratedRootsParent($id){
		$root = $this->getGeneratorsRootByGeneratedRootsParent($id);
		//$this->log($root);
		$childs = $this->getChildsIds($root,false,'generator_options');
		//$this->log(json_encode($childs));
		if(empty($childs))return array();
		return $this->modx->getIterator('modResource',array('id:IN'=>$childs),false);
	}
	
	//Получение итератора по герерируемым для генератора
	public function getGeneratorGeneratedIterator($generator_id){
		$q = $this->modx->newQuery('modResource',null,false);
		$q->innerJoin('modTemplateVarResource','tvr','modResource.id=tvr.contentid AND tvr.value="'.$generator_id.'"');
		$q->innerJoin('modTemplateVar','tv','tv.id=tvr.tmplvarid AND tv.name="generator"');
		return $this->modx->getIterator('modResource',$q,false);
	}
	
	//Составление соотвествия для генератора генерируемых ресусров с указанием их корневого ресурса
	public function getGeneratorGeneratedMap($generator_id){
		if($map = $this->__cache['GeneratorGeneratedMap'][$generator_id])return $map;
		$map = array();

		$q = $this->modx->newQuery('modTemplateVarResource',null,false);
		$q->innerJoin('modTemplateVarResource','tvr','modTemplateVarResource.id=tvr.id AND tvr.value="'.$generator_id.'"');
		$q->innerJoin('modTemplateVar','tv','tv.id=tvr.tmplvarid AND tv.name="generator"');
		$q->leftJoin('modResource','l0','l0.id=tvr.contentid');
		$iterations=$this->modx->getOption('pgenerator.join_iterations',null,10,true);
		$selects=array('l0.id');
		for($i=1;$i<=$iterations;$i++){
			$q->leftJoin('modResource','l'.$i,'l'.$i.'.id=l'.($i-1).'.parent');
			$selects[]='l'.$i.'.id';
		}
		$q->select($selects);
		$q->prepare();
		//$this->log($q->toSQL());
		$q->stmt->execute();
		
		$roots_parent = $this->getGeneratedRootsParentByGenerator($generator_id);
		while($row = $q->stmt->fetch(PDO::FETCH_NUM)){
			$parents=array_map('intval',array_diff($row,array(null)));
			$idx = array_search($roots_parent,$parents);
			if($idx===false)continue;
			$map[$parents[$idx-1]]=$row[0];
		}
		
		$this->__cache['GeneratorGeneratedMap'][$generator_id] = $map;
		
		return $map;
	}
	
	//Получение генератора по генерирумому ресурсу
	public function getGeneratedGenerator(&$generated){
		$tvrs = $this->getResourcesTVValues(array($generated->id));
		$generator_id = $tvrs[$generated->id]['generator'];
		//$this->log($generator_id.' -- '.json_encode($tvrs));
		if(!$generator_id)return false;
		return $this->modx->getObject('modResource',(int)$generator_id,false);
	}
	
	//Получение корня по генерирумому ресурсу
	public function getGeneratedRoot(&$generated){
		$roots = $this->getGeneratedRootsParents();
		$parents = $this->getParentsIds($generated->id?:$generated->parent,!$generated->id);
		$roots_parent = current(array_intersect($roots,$parents));
		$idx = array_search($roots_parent,$parents);
		//$this->log($roots_parent.' -- '.json_encode($parents).' -- '.(int)$parents[$idx-1]);
		return $this->modx->getObject('modResource',array('id'=>(int)$parents[$idx-1]),false);
	}
	
	//Находит генерируемый ресурс в определённом корне для генератора
	public function getGeneratorGeneratedInRoot($generator_id,$root_id){
		$childs = $this->getChildsIds($root_id,false,'generator');
		$q = $this->modx->newQuery('modResource',array('id:IN'=>$childs),false);
		$q->innerJoin('modTemplateVarResource','tvr','modResource.id=tvr.contentid AND tvr.value="'.$generator_id.'"');
		$q->innerJoin('modTemplateVar','tv','tv.id=tvr.tmplvarid AND tv.name="generator"');
		$q->select(array('modResource.id'));
		$q->prepare();
		$q->stmt->execute();
		return $this->modx->getValue($q->stmt);
	}
	
	
	
	
	
	
	
	public function generatePage(&$generator,&$root){
		$disabled=$generator->getTVValue('generator_disabled');
		if($disabled)return false;
		
		//1. Собираем карту для генератора через modTemplateVarResource c join ресурсов и родителей
		$generated_map=$this->getGeneratorGeneratedMap($generator->id);
		//$this->log(print_r($generated_map,1));
		
		//2. Если $root есть в карте, используем id ресурса из карты
		$generated_id=$generated_map[$root->id];
		//3. Если $root нет в карте, то собираем карту до генератора и до нужного ресурса от root
		if(!$generated_id){
			$generated_parent_id = $root->id;
			if($this->getGeneratorsRoot($generator->id)!=$generator->parent){
				$generated_parent_map=$this->getGeneratorGeneratedMap($generator->parent);//ignore cache?
				$generated_parent_id=$generated_map[$root->id];
				if(!$generated_parent_id){
					$generator_parent = $this->modx->getObject('modResource',$generator->parent,false);
					$generated_parent = $this->generatePage($generator_parent,$root);
					if(!$generated_parent)return false;
					$generated_parent_id = $generated_parent->id;
				}
			}
			$generated=$this->modx->newObject('modResource');
			$generated->parent=$generated_parent_id;
		}else{
			$generated=$this->modx->getObject('modResource',$generated_id,false);
		}
		
		//5. Генерируем ресурс
		$generator_fields=$generator->toArray();
		$root_fields=$root->toArray();
		$generated_fields=$generated->toArray();
		
		$tvr = $this->getResourcesTVValues(array($generator->id,$root->id,$generated->id),'tv.');
		//$this->log(json_encode($tvr[$generator->id]));
		$generator_fields=array_merge($generator_fields,$tvr[$generator->id]?:array());
		$root_fields=array_merge($root_fields,$tvr[$root->id]?:array());
		$generated_fields=array_merge($generated_fields,$tvr[$generated->id]?:array());
		
		$placeholders=array();
		$placeholders=array_merge($placeholders,$this->extractPlaceholders($generator_fields,'generator_'));
		$placeholders=array_merge($placeholders,$this->extractPlaceholders($root_fields,'root_'));
		
		$generatorOptions=$this->modx->fromJSON($generator_fields['tv.generator_options'])?:array();
		$generatorOptions=array_combine(array_values(array_column($generatorOptions,'field')),array_values($generatorOptions));
		
		if($generated->isNew()){
			//Надо определить шаблон и получить tv и значения по умолчанию для шаблона
			//Шаблон это шаблон generator либо указанный в options
			$generated_tpl=$generator->template;
			if($generatorOptions['temlplate']&&$generatorOptions['temlplate']['apply']==1&&$generatorOptions['temlplate']['value']!=''){
				$generated_tpl=$this->processText($generatorOptions['temlplate']['value'],$placeholders);
			}
			$generated_fields['template']=$generated_tpl;
			$generatedTpl=$this->getTemplates(array($generated_tpl));
			$generatedTVs=$generatedTpl[$generated_tpl]['tvs'];
			foreach($generatedTVs as $generatedTV){
				$generated_fields['tv.'.$generatedTV['name']]=$generatedTV['default_text'];
			}
		}
		
		$placeholders=array_merge($placeholders,$this->extractPlaceholders($generated_fields));
		//$this->log(json_encode($placeholders));
		
		$generatedOptions=$this->modx->fromJSON($generated_fields['tv.generated_options'])?:array();
		//$this->log(json_encode($generated_fields));
		$generatedOptions=array_combine(array_values(array_column($generatedOptions,'field')),array_values($generatedOptions));
		$total_fields=$generator_fields;
		$exclude_fields=array(/*'searchable',*/'parent','tv.generator','tv.generator_options','tv.generated_options');
		foreach($total_fields as $tfk=>$total_field){
			if(in_array($tfk,$exclude_fields)){
				unset($total_fields[$tfk]);
				continue;
			}
			if(isset($generatedOptions[$tfk])&&$generatedOptions[$tfk]['apply']!=1){
				unset($total_fields[$tfk]);
				continue;
			}
			if(isset($generatorOptions[$tfk])&&$generatorOptions[$tfk]['apply']!=1){
				unset($total_fields[$tfk]);
				continue;
			}
			if(isset($generatorOptions[$tfk])&&$generatorOptions[$tfk]['apply']==1&&$generatorOptions[$tfk]['value']!=''){
				$total_fields[$tfk]=$this->processText($generatorOptions[$tfk]['value'],$placeholders);
				if($tfk=='alias'){
					$total_fields[$tfk] = $generator->cleanAlias($total_fields[$tfk]);
				}
			}
		}
		unset($tfk);unset($total_field);
		
		$generated->fromArray($total_fields);
		$total_fields['tv.generator']=$generator->id;
		$this->setResourcesTVValues($generated,$total_fields,'tv.');
		
		//$this->log(print_r($generated->toArray('',false,false,true),1));
		
		if(!$generated->save(false))return false;
		$this->__cache['GeneratorGeneratedMap'][$generator->id][$root->id]=$generated->id;
		return $generated;
	}
	
	public function setResourcesTVValues(&$resource,$tvs=array(),$prefix=false){
		$this->getTemplates(array($resource->template));
		$tvrs=$resource->getMany('TemplateVarResources');
		$__tvrs=array();
		foreach($tvrs as $tvr){
			$__tvrs[]=$tvr->tmplvarid;
			$tvrs['tv_'.$tvr->tmplvarid]=$tvr;
		}
		$_tvs=array_column($this->__cache['TemaplteVar'],'id','name');
		foreach($tvs as $tvname=>$value){
			if($prefix&&strpos($tvname,$prefix)!==0)continue;
			if($prefix)$tvname=substr($tvname,strlen($prefix));
			$tmplvarid=$_tvs[$tvname];
			$tv=$this->__cache['TemaplteVar'][$tmplvarid];
			if($tv['default_text']==$value){
				if(isset($tvrs['tv_'.$tmplvarid]))$tvrs['tv_'.$tmplvarid]->remove();
				continue;
			}
			//$this->log($tvname.' - '.$tmplvarid);
			//$this->log($value);
			$__tvr=$tvrs['tv_'.$tmplvarid]?:$this->modx->newObject('modTemplateVarResource',array('tmplvarid'=>(int)$tmplvarid));
			//$this->log($__tvr->tmplvarid);
			$__tvr->set('value',$value);
			$resource->addMany($__tvr,'TemplateVarResources');
		}
	}
	
}
