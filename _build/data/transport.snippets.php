<?php

$sconfig=[
    'isGenerator'=>[
        'description'=>'Return true if resource is generator',
    ],
    'getGenerated'=>[
        'description'=>'Return json of fields for generated resource by generator and root id',
    ],
];

foreach($sconfig?:[] as $snippet=>$options){
    $snippet_file=$config['component']['core'].'elements/snippets/'.$snippet.'.php';
    if(!file_exists($snippet_file))continue;
    $data['modSnippet'][$snippet]=[
        'fields'=>[
            'name' => $snippet,
            'description' => $options['description'],
            'snippet' => trim(str_replace(['<?php', '?>'], '', file_get_contents($snippet_file))),
            'source' => 1,
        ],
        'options'=>$config['data_options']['modSnippet'],
        'relations'=>[
            'modCategory'=>[
                'main'=>'Snippets'
            ]
        ]
    ];
}