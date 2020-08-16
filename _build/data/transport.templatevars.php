<?php

$data['modTemplateVar']=[
    'generator'=>[
        'fields'=>[
            'type' => 'text',
            'name' => 'generator',
            'caption' => $config['component']['namespace'].'.tv.caption.generator',
        ],
        'options'=>$config['data_options']['modTemplateVar'],
        'relations'=>[
            'modCategory'=>[
                'main'=>'TemplateVars'
            ]
        ]
    ],
    'generated_options'=>[
        'fields'=>[
            'type' => 'migx',
            'name' => 'generated_options',
            'caption' => $config['component']['namespace'].'.tv.caption.generated_options',
            'description' => $config['component']['namespace'].'.tv.description.generated_options',
            'default_text' => '[{"MIGX_id":"1","field":"pagetitle","apply":"1"},{"MIGX_id":"2","field":"alias","apply":"1"}]',
            'input_properties' => 'a:7:{s:7:"configs";s:17:"generated_options";s:8:"formtabs";s:0:"";s:7:"columns";s:0:"";s:7:"btntext";s:0:"";s:10:"previewurl";s:0:"";s:10:"jsonvarkey";s:0:"";s:19:"autoResourceFolders";s:5:"false";}',
        ],
        'options'=>$config['data_options']['modTemplateVar'],
        'relations'=>[
            'modCategory'=>[
                'main'=>'TemplateVars'
            ]
        ]
    ],
    'generator_disabled'=>[
        'fields'=>[
            'type' => 'checkbox',
            'name' => 'generator_disabled',
            'caption' => $config['component']['namespace'].'.tv.caption.generator_disabled',
            'elements' => 'Да==1',
            'default_text' => '1',
        ],
        'options'=>$config['data_options']['modTemplateVar'],
        'relations'=>[
            'modCategory'=>[
                'main'=>'TemplateVars'
            ]
        ]
    ],
    'generator_options'=>[
        'fields'=>[
            'type' => 'migx',
            'name' => 'generator_options',
            'caption' => $config['component']['namespace'].'.tv.caption.generator_options',
            'description' => $config['component']['namespace'].'.tv.description.generator_options',
            'default_text' => '[{"MIGX_id":"1","field":"pagetitle","value":"<$$generator_pagetitle$> <$$root_id|resource:\'pagetitle\'$>","apply":"1"},{"MIGX_id":"2","field":"alias","value":"<$$generator_alias$>-<$$root_id|resource:\'pagetitle\'$>","apply":"1"}]',
            'input_properties' => 'a:7:{s:7:"configs";s:17:"generator_options";s:8:"formtabs";s:0:"";s:7:"columns";s:0:"";s:7:"btntext";s:0:"";s:10:"previewurl";s:0:"";s:10:"jsonvarkey";s:0:"";s:19:"autoResourceFolders";s:5:"false";}',
        ],
        'options'=>$config['data_options']['modTemplateVar'],
        'relations'=>[
            'modCategory'=>[
                'main'=>'TemplateVars'
            ]
        ]
    ],
];