<?php

namespace App\Http\Controllers;

use App\Models\Mensagem;

class LogController extends Controller
{
    private function RegistrarLog(string $linha) {
        try {
            if(!is_dir("../storage/app/logs")){
                mkdir("../storage/app/logs", 0700);
            }
            $arquivo = fopen("../storage/app/logs/registros_" . date("Y-m-d") . ".txt", "a");
            fwrite($arquivo, $linha );
            fwrite($arquivo, "\n");
            fclose($arquivo);
        } catch (\Throwable $th) {
            // ???? EM CASO DE FALHAS DISPARA UM EMAIL AOS ADMINISTRADORES DO WEBSERVICE
        }
    }

    private function CriarObjLog(int $codigo) : object{
        date_default_timezone_set('America/Sao_Paulo');
        $log            = (object)[];
        $log->data_hora = date("Y-m-d H:i:s");
        $log->codigo    = $codigo;
        $log->descricao = Mensagem::getMensagem($codigo);
        return $log;
    }

    static function InicioExecucao(){
        self::RegistrarLog(json_encode(self::CriarObjLog(1), JSON_UNESCAPED_UNICODE));
    }

    static function FimExecucao(){
        self::RegistrarLog(json_encode(self::CriarObjLog(2), JSON_UNESCAPED_UNICODE));
    }

    static function SucessoCriarCategoria(object $categoria){
        $log            = self::CriarObjLog(201);
        $log->idnumber  = $categoria->idnumber;
        $log->nome      = $categoria->name;
        self::RegistrarLog(json_encode($log, JSON_UNESCAPED_UNICODE));
    }

    static function SucessoCriarCurso(object $curso){
        $log            = self::CriarObjLog(202);
        $log->idnumber  = $curso->idnumber;
        $log->nome_breve = $curso->shortname;
        self::RegistrarLog(json_encode($log, JSON_UNESCAPED_UNICODE));
    }

    static function SucessoCriarGrupo(object $grupo){
        $log            = self::CriarObjLog(203);
        $log->idnumber  = $grupo->idnumber;
        $log->nome      = $grupo->name;
        self::RegistrarLog(json_encode($log, JSON_UNESCAPED_UNICODE));
    }

    static function SucessoCriarCoorte(object $coorte){
        $log            = self::CriarObjLog(204);
        $log->idnumber  = $coorte->idnumber;
        $log->nome      = $coorte->name;
        self::RegistrarLog(json_encode($log, JSON_UNESCAPED_UNICODE));
    }

    static function SucessoCriarUsuario(object $usuario){
        $log            = self::CriarObjLog(205);
        $log->idnumber  = $usuario->idnumber;
        $log->cpf_usuario  = $usuario->username;
        self::RegistrarLog(json_encode($log, JSON_UNESCAPED_UNICODE));
    }

    static function SucessoCriarVinculoUsuarioCurso(object $vin_usuario_curso){
        $log                    = self::CriarObjLog(206);
        $log->cpf_usuario       = $vin_usuario_curso->username;
        $log->idnumber_curso    = $vin_usuario_curso->idnumber_curso;
        self::RegistrarLog(json_encode($log, JSON_UNESCAPED_UNICODE));
    }

    static function SucessoCriarVinculoUsuarioGrupo(object $vin_usuario_grupo){
        $log                    = self::CriarObjLog(207);
        $log->cpf_usuario       = $vin_usuario_grupo->username;
        $log->idnumber_grupo    = $vin_usuario_grupo->idnumber_grupo;
        self::RegistrarLog(json_encode($log, JSON_UNESCAPED_UNICODE));
    }

    static function SucessoCriarVinculoUsuarioCoorte(object $vin_usuario_coorte){
        $log                    = self::CriarObjLog(208);
        $log->cpf_usuario       = $vin_usuario_coorte->usertype->value;
        $log->idnumber_coorte   = $vin_usuario_coorte->cohorttype->value;
        self::RegistrarLog(json_encode($log, JSON_UNESCAPED_UNICODE));
    }

    static function ErroAPIMoodle(object $excecao, string $funcao, object $registro = null) {
        $log            = self::CriarObjLog(401);
        $log->funcao    = $funcao;
        if($registro != null){
            // EM CASO DE FUNÇÕES DE CONSULTAR?
            switch ($funcao) {
                case 'criarCategoriaRaiz':
                    $log->idnumber  = $registro->idnumber;
                    $log->nome      = $registro->name;
                    break;
                case 'criarCategorias':
                    $log->idnumber  = $registro->idnumber;
                    $log->nome      = $registro->name;
                    break;
                case 'criarCursos':
                    $log->idnumber      = $registro->idnumber;
                    $log->nome_breve    = $registro->shortname;
                    break;
                case 'criarUsuarios':
                    $log->idnumber      = $registro->idnumber;
                    $log->cpf_usuario   = $registro->username;
                    break;
                case 'criarGrupos':
                    $log->idnumber  = $registro->idnumber;
                    $log->nome      = $registro->name;
                    break;
                case 'criarCoortes':
                    $log->idnumber  = $registro->idnumber;
                    $log->nome      = $registro->name;
                    break;
                case 'criarVinculosUsuarioCurso':
                    $log->cpf_usuario       = $registro->username;
                    $log->idnumber_curso    = $registro->idnumber_curso;
                    break;
                case 'criarVinculosUsuarioGrupo':
                    $log->cpf_usuario       = $registro->username;
                    $log->idnumber_grupo    = $registro->idnumber_grupo;
                    break;
                case 'criarVinculosUsuarioCoorte':
                    $log->cpf_usuario       = $registro->usertype->value;
                    $log->idnumber_coorte   = $registro->cohorttype->value;
                    break;
            }
        }
        if(isset($excecao->exception)){
            $log->excecao = $excecao->exception;
        }
        if(isset($excecao->message)){
            $log->mensagem = $excecao->message;
        }
        self::RegistrarLog(json_encode($log, JSON_UNESCAPED_UNICODE));
    }

    static function ErroCriarUsuarioMoodleEmailInvalido(object $pessoa_sei) {
        $log                = self::CriarObjLog(402);
        $log->cpf_usuario   = $pessoa_sei->cpf;
        $log->email_usuario = $pessoa_sei->email;
        self::RegistrarLog(json_encode($log, JSON_UNESCAPED_UNICODE));
    }

    static function ErroCriarVinculoMoodleUsuarioNaoExiste(object $pessoa_sei) {
        $log                = self::CriarObjLog(403);
        $log->cpf_usuario   = $pessoa_sei->cpf;
        $log->email_usuario = $pessoa_sei->email;
        self::RegistrarLog(json_encode($log, JSON_UNESCAPED_UNICODE));
    }
}
