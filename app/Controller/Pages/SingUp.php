<?php

namespace App\Controller\Pages;

use \App\Utils\View;
use \App\Models\Entity\User as EntityUser;

class SingUp extends Page {

    /**
     * Metodo responsavel por retornar o contéudo (view) da pagina cadastro
     * @return string 
     */
    public static function getSingUp($request) {
        // CONTEUDO DA PAGINA DE LOGIN
        $content = View::render('pages/singup', [
            'status' => Alert::getStatus($request)
        ]);
        // RETORNA A VIEW DA PAGINA
        return parent::getHeader('Cadastro', $content);
    }

    public static function setSingUp($request) {
        // POST VARS
        $postVars = $request->getPostVars();

        $nome     = $postVars['nome'] ?? '';
        $email    = $postVars['email'] ?? '';
        $password = $postVars['senha'] ?? '';
        $confirm  = $postVars['senhaConfirma'] ?? '';

        // VALIDA O NOME
        if (EntityUser::validateUserName($nome)) {
            $request->getRouter()->redirect('/singup?status=invalid_name');
        }

        // VALIDA O EMAIL
        if (EntityUser::validateUserEmail($email)) {
            $request->getRouter()->redirect('/singup?status=invalid_email');
        }
        // VALIDA A SENHA
        if (EntityUser::validateUserPassword($password, $confirm)) {
            $request->getRouter()->redirect('/singup?status=invalid_pass');
        }

        // VERIFICA O EMAIL DO USUÁRIO
        $obUser = EntityUser::getUserByEmail($email);

        // VERIFICA SE O EMAIL ESTA DISPONÍVEL
        if ($obUser instanceof EntityUser) {
            $request->getRouter()->redirect('/singup?status=duplicated_email');
        }

        // NOVA INSTÂNCIA DE USUARIO
        $obUser = new EntityUser;
        $obUser->nom_usuario = $nome;
        $obUser->email = $email;
        $obUser->senha = password_hash($password, PASSWORD_DEFAULT);

        // CADASTRA O USUÁRIO
        $obUser->insertUser();

        // REDIRECIONA O USUARIO
        $request->getRouter()->redirect('/singin?status=user_registered');
    }
}