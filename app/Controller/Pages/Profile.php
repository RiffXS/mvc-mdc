<?php

namespace App\Controller\Pages;

use App\Http\Request;
use App\Models\Turma as EntityGrade;
use App\Models\Aluno as EntityStudent;
use App\Models\Professor as EntityTeacher;
use App\Models\Usuario as EntityUser;
use App\Utils\Tools\Alert;
use App\Utils\Sanitize;
use App\Utils\Session;
use App\Utils\Upload;
use App\Utils\View;

class Profile extends Page {

    /**
     * Método responsável por retornar o contéudo (view) da página perfil
     * @param \App\Http\Request $request
     * 
     * @return string
     */
    public static function getEditProfile(Request $request) {
        // OBTEM A IMAGEM DO USUARIO
        $obUser = EntityUser::getUserById(Session::getId());

        $view = self::getTextType($obUser);

        // VIEW DA HOME
        $content = View::render('pages/profile', [
            'status' => Alert::getStatus($request),
            'foto'   => $obUser->getImg_perfil(),
            'nome'   => $obUser->getNom_usuario(),
            'email'  => $obUser->getEmail(),
            'texto'  => $view['text'],
            'campo'  => $view['colum']
        ]);
        // RETORNA A VIEW DA PAGINA
        return parent::getPage('Perfil', $content);
    }

    /**
     * Método responsável por definir o texto de acordo com o tipo de usuário
     * @param \App\Models\Usuario $obUser
     * 
     * @return array
     */
    public static function getTextType(EntityUser $obUser): array {
        // DECLARAÇÃO DE VARIAVEIS
        $text = '';
        $colum = '';

        // RELATIVO NIVEL DE ACESSO DO USUARIO
        switch ($obUser->getFk_acesso()) {
            case 2:
                $text = 'Matricula';
                $colum = View::render('pages/components/profile/enrollment');

                break;

            case 3:
                $class = EntityUser::getUserClass(Session::getId());
                $text = 'Turma';
                
                if (!empty($class)) {
                    $colum = View::render('pages/components/profile/class', [
                        'curso'  => $class['curso'],
                        'modulo' => $class['modulo']
                    ]);
                }
                break;

            case 4:
                $text = 'Setor';
                $colum = View::render('pages/components/profile/sector');
                
                break;
                
            case 5:
                $obTeacher = EntityTeacher::getTeacherById($obUser->getId_usuario());
                $text = 'Regras';

                $colum = View::render('pages/components/profile/rules', [
                    'regras' => $obTeacher->getRules()
                ]);

                break;            
        }
        // RETORNA O TEXTO E A VIEW DA COLUNA
        return [
            'text'  => $text,
            'colum' => $colum
        ];
    }

    /**
     * Método responsável por atualizar o perfil do usuário
     * @param \App\Http\Request $request
     * 
     * @return void
     */
    public static function setEditProfile(Request $request): void {
        // POST VARS    
        $postVars = $request->getPostVars();

        // VERIFICA HTML INJECT
        if (Sanitize::validateForm($postVars)) {
            $request->getRouter()->redirect('/signup?status=invalid_chars');
        }
        // SANITIZA O ARRAY
        $postVars = Sanitize::sanitizeForm($postVars);
        $files = $request->getUploadFiles();

        // OBTEM O USUARIO E O NIVEL DE ACESSO DA SESSÃO
        $obUser = Session::getUser();
    
        // LAMBDA - VERIFICA SE EXISTE UM STATUS DE ERRO
        $isError = function($key) use ($request): void {
            if (!empty($key)) {
                $request->getRouter()->redirect("/profile?status=$key");
            }
        };

        // NOVA INSTANCIA 
        $obUpload = new Upload($files['foto']);

        $photo = $obUser->getImg_perfil();

        // Valida a entrada do arquivo para verificar se não está vazio
        if (file_exists($obUpload->getTpmName())) {
            $info = pathinfo($photo);

            if ($photo == 'user.png') {
                $obUpload->generateNewName();
            } 
            else {
                if ($obUpload->getExtension() != $info['extension']) {
                    unlink(__DIR__."/../../../public/uploads/".$photo);
                }
                // ATRIBUI O NOME AO JA EXISTENTE DO USUARIO
                $obUpload->setName($photo);
            }
            $isError(self::updateProfilePicture($obUpload));
        }    
        $photo = $obUpload->getBasename();

        // ATUALIZA O CAMPO DO TIPO USUARIO
        self::updateProfileUser($request, $obUser, $postVars);


        $nome = $postVars['nome'];
        $email = $postVars['email'];

        // VALIDA O NOME
        if (Sanitize::validateName($nome)) {
            $request->getRouter()->redirect('/profile?status=invalid_name');
        }
        // VALIDA O EMAIL
        if (Sanitize::validateEmail($email)) {
            $request->getRouter()->redirect('/profile?status=invalid_email');
        }
        // VALIDA O EMAIL DO USUARIO
        $obUserEmail = EntityUser::getUserByEmail($email);

        if ($obUserEmail instanceof EntityUser && $obUserEmail->getId_usuario() != $obUser->getId_usuario()) {
            $request->getRouter()->redirect('/profile?status=duplicated_email');
        }
        // ATUALIZA A INSTÂNCIA
        $obUser->setNom_usuario($nome);
        $obUser->setEmail($email);
        $obUser->setImg_perfil($photo);
        
        // ATUALIZA O USUÁRIO
        $obUser->updateUser();

        // REDIRECIONA O USUÁRIO
        $request->getRouter()->redirect('/profile?status=profile_updated');
    }

    /**
     * Método responsavel por realizar o upload da imagem enviada pelo usuario
     * @param \App\Http\Request $request
     * @param \App\Utils\Upload $photo
     * @param array $file
     * 
     * @return string
     */
    private static function updateProfilePicture(Upload $obUpload): string {
        $extensions = array("png", "jpg", "jpeg");

        $status = '';

        // VALIDA SE A EXTENSÃO DO ARQUIVO É PERMITIDA
        if (!in_array($obUpload->getExtension(), $extensions)) {
            $status = 'image_type';
        }
        // VALIDA SE O TAMANHO DA IMAGEM EXCEDEU O LIMITE
        else if ($obUpload->getsize() > $_POST['MAX_FILE_SIZE']) {
            $status = 'image_syze';
        }
        // VALIDA SE O UPLOAD OCORREU CORRETAMENTE
        else if ((!$obUpload->upload(__DIR__.'/../../../public/uploads/'))){    
            $status = 'image_erro';
        } 
        // RETORNA O STATUS
        return $status;
    } 

    /**
     * Método responsavel por realizar uma operação relativo ao tipo de usuario atual
     * @param \App\Http\Request $request
     * @param \App\Models\Usuario  $obUser
     * @param array $postVars
     */
    private static function updateProfileUser(Request $request, EntityUser $obUser, array $postVars) {
        // REALIZA UMA AÇÃO DEPENDENDO DO TIPO DE USUARIO
        switch (Session::getLv()) {
            // USUARIO
            case 2:
                if (!empty($postVars['matricula'])) {
                    // NOVA INSTANCIA
                    $obStudent = new EntityStudent;
        
                    $obStudent->setFk_id_usuario($obUser->getId_usuario());
                    $obStudent->setNum_matricula($postVars['matricula']);
                
                    // VERIFICA SE A MATRICULA ESTA DISPONIVEL
                    if (!$obStudent->verifyEnrollment()) {
                        $status = 'enrollment_duplicated';
                    } 
                    // INSERE O USUARIO NA TABELA DE ALUNOS
                    $obStudent->insertStudent();
                }
                // ALTERA O NIVEL DE ACESSO PARA 3 (ALUNO)
                $obUser->setFk_acesso(3); 
                
                break;
                
            // ALUNO
            case 3:
                // VERIFICA SE O CURSO E O MODULO FORAM RECEBIDOS
                if (!empty($postVars['curso']) && !empty($postVars['modulo'])) {
                    // BUSCA O ID DA TURMA POR CURSO E MODULO
                    $gradeId = EntityGrade::getGradeId($postVars['curso'], $postVars['modulo']);

                    // NOVA INSTANCIA
                    $obStudent = new EntityStudent;
 
                    $obStudent->setFk_id_usuario($obUser->getId_usuario());
                    $obStudent->setFk_id_turma($gradeId['id_turma']);

                    $obStudent->updateStudent(); // ATUALIZA A TURMA DO ALUNO
                }
                break;

            // SERVIDOR
            case 4:
                break;

            // PROFESSOR
            case 5:
                // VERIFICA SE O CAMPO ESTA VAZIO
                if (!empty($postVars['regras'])) {
                    // NOVA INSTANCIA
                    $obTeacher = new EntityTeacher();

                    $obTeacher->setFk_id_usuario($obUser->getId_usuario());
                    $obTeacher->setRules($postVars['regras']);

                    $obTeacher->updateRules(); // ATUALIZA AS REGRAS DO PROFESSOR
                }         
                break;
        }
    }
}
