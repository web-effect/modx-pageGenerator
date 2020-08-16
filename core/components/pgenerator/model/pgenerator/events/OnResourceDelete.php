<?php

namespace PGenerator\Events;

class OnResourceDelete extends Event
{
    public function run()
    {
        $id=$this->scriptProperties['id'];
        $children=$this->scriptProperties['children'];
        $resource=$this->scriptProperties['resource'];
        
        if($this->cmp->isGenerator($id)){
			$ids = array_merge($children,[$id]);
			//$this->modx->log(1,json_encode($ids));
			foreach($ids as $did){
				$iterator = $this->cmp->getGeneratorGeneratedIterator($did);
				$iterator->rewind();
				if(!$iterator->valid())break;
				foreach($iterator as $generated){
					$generated->set('deleted',true);
					$generated->set('deletedby',$resource->deletedby);
					$generated->set('deletedon',$resource->deletedon);
					$generated->save(false);
				}
			}
		}
    }

}
