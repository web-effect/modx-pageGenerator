<?php

namespace PGenerator\Events;

class OnResourceUndelete extends Event
{
    public function run()
    {
        $id=$this->scriptProperties['id'];
        $children=$this->scriptProperties['children'];
        $resource=$this->scriptProperties['resource'];
        
        if($this->cmp->isGenerator($id)){
			$ids = $this->cmp->getChildsIds($id,true);
			//$this->modx->log(1,json_encode($ids));
			foreach($ids as $did){
				$iterator = $this->cmp->getGeneratorGeneratedIterator($did);
				$iterator->rewind();
				if(!$iterator->valid())break;
				foreach($iterator as $generated){
					$generated->set('deleted',false);
					$generated->set('deletedby',0);
					$generated->set('deletedon',0);
					$generated->save(false);
				}
			}
		}
    }

}
