<?php

namespace PGenerator\Events;

class OnResourceTVFormRender extends Event
{
    public function run()
    {
        $resource=$this->scriptProperties['resource'];
        $categories=$this->scriptProperties['categories'];
        $resource_id = (int)$this->modx->controller->resourceArray['id']?:(int)$this->modx->controller->resourceArray['parent'];
        
		$hide_tvs=array("generator");
		if(!$this->cmp->isGenerator($resource_id,$resource?false:true))$hide_tvs=array_merge($hide_tvs,["generator_disabled","generator_options"]);
		if(!$this->cmp->isGenerated($resource_id,$resource?false:true))$hide_tvs=array_merge($hide_tvs,["generated_options"]);

		foreach ($categories as $idc => $category) {
			foreach ($category['tvs'] as $idt => $tv) {
				if(in_array($categories[$idc]['tvs'][$idt]->name,$hide_tvs)){
					$categories[$idc]['tvs'][$idt]->set('type','hidden');
					$inputForm = $categories[$idc]['tvs'][$idt]->renderInput(null, array('value'=> $categories[$idc]['tvs'][$idt]->get('value')));
					$categories[$idc]['tvs'][$idt]->set('formElement',$inputForm);
					$hidden[]=$categories[$idc]['tvs'][$idt];
				}
				$categories[$idc]['tvs'][$idt]->caption=$this->modx->lexicon($tv->caption)?:$tv->caption;
				$categories[$idc]['tvs'][$idt]->description=$this->modx->lexicon($tv->description)?:$tv->description;
			}
		}
		$this->modx->event->_output='';
    }

}
