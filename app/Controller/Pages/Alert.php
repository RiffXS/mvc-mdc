<?php

namespace App\Controller\Pages;

use App\Utils\View;
use App\Utils\Json;

class Alert {

    /**
     * Methodo responsavel por retornar uma mensagem de erro
     * @param  string $message
     * @return string
     */
    public static function getMessage($type, $text) {
        return View::render('admin/alert/status', [
            'tipo'     => $type,
            'mensagem' => $text
        ]);
    }

    /**
     * Methodo responsavel por retornar a menagem de status
     * @param \App\Http\Request
     * @return string
     */
    public static function getStatus($request) {
        // QUERY PARAMS
        $queryParams = $request->getQueryParams();
        
        // VERIFICA SE EXISTE UM STATUS
        if (isset($queryParams['status'])) {
            // OBTEM A CHAVE DO STATUS
            $codigo = $queryParams['status'];
            
            // OBTEM O ARRAY DE MENSAGEMS
            $mensagems = Json::getContent('status');

            $tipo  = $mensagems[$codigo]['type']; // Tipo de mensagem
            $texto = $mensagems[$codigo]['text']; // Texto da mensagem

            // RETORNA A VIEW 
            return self::getMessage($tipo, $texto);
        }
        // RETORNA UM VAZIO
        return '';
    }
}