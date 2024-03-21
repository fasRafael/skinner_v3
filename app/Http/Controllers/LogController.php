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
            $arquivo = fopen("../storage/app/logs/registros_separados_" . date("Y-m-d") . ".txt", "a");
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

    static function SucessoCriarCategoria(object $categoria){
        $log            = self::CriarObjLog(201);
        $log->idnumber  = $categoria->idnumber;
        $log->name      = $categoria->name;
        self::RegistrarLog(json_encode($log, JSON_UNESCAPED_UNICODE));
    }

    static function SucessoCriarCurso(object $curso){
        $log            = self::CriarObjLog(202);
        $log->idnumber  = $curso->idnumber;
        $log->shortname = $curso->shortname;
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
        $log->idnumber        = $usuario->idnumber;
        $log->username  = $usuario->username;
        self::RegistrarLog(json_encode($log, JSON_UNESCAPED_UNICODE));
    }

    static function SucessoCriarVinculoUsuarioCurso(object $vin_usuario_curso){
        $log            = self::CriarObjLog(206);
        // CARREGAR IDNUMER
        $log->userid    = $vin_usuario_curso->userid;
        $log->courseid  = $vin_usuario_curso->courseid;
        self::RegistrarLog(json_encode($log, JSON_UNESCAPED_UNICODE));
    }

    static function SucessoCriarVinculoUsuarioGrupo(object $vin_usuario_grupo){
        $log            = self::CriarObjLog(207);
        // CARREGAR IDNUMER
        $log->userid    = $vin_usuario_grupo->userid;
        $log->groupid   = $vin_usuario_grupo->groupid;
        self::RegistrarLog(json_encode($log, JSON_UNESCAPED_UNICODE));
    }

    static function SucessoCriarVinculoUsuarioCoorte(object $vin_usuario_coorte){
        $log            = self::CriarObjLog(208);
        // CARREGAR IDNUMER
        $log->userid    = $vin_usuario_coorte->usertype->value;
        $log->chortid   = $vin_usuario_coorte->cohorttype->value;
        self::RegistrarLog(json_encode($log, JSON_UNESCAPED_UNICODE));
    }

    // ??? CAPTURAR A LINHA E A FUNÇÃO EM CASO DE FALHAS
    static function ErroAPIMoodle(object $excecao) {
        $log                = self::CriarObjLog(401);
        $log->excecao       = $excecao->exception;
        $log->codigo_erro   = $excecao->errorcode;
        $log->mensagem      = $excecao->message;
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
