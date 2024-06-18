<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class MoodleController extends Controller
{
    #region Constantes de acesso ao moodle
    /*const URL_API_MOODLE = 'https://ava.faculdadesensu.edu.br/webservice/restjson/server.php';
    const TOKEN_API_MOODLE = "7535f43e6ea21e1ce02f6a0ed8dbd565";*/
    const URL_API_MOODLE = 'http://10.1.1.6/moodle/webservice/rest/server.php';
    const TOKEN_API_MOODLE = "6f5ad94d2d9f79791a520cf3bb6b2a78";
    const ID_NUMBER_CATEGORIA_RAIZ = "SKINNER_V3";
    #endregion

    public $categoria_raiz;

    function __construct(){
        $this->categoria_raiz = $this->consultaCategoriaRaiz();
    }

    /**
     * Realiza a requisição para o webservice do moodle conforme a função informada via parâmetro e as constantes de acesso ao moodle
     * @param string $funcao Responsável por informar ao webservice moodle qual método será executado
     * @param array $lista_parametros array composto pelas variáveis que serão passadas ao método do webservice do moodle
     */
    private function enviarRequisicaoMoodle(string $funcao, array $lista_parametros = []) {
        $parametros['wstoken'] = $this::TOKEN_API_MOODLE;
        $parametros['moodlewsrestformat'] = 'json';
        $parametros['wsfunction'] = $funcao;
        foreach ($lista_parametros as $parametro_chave => $parametro_valor) {
            $parametros[$parametro_chave] = $parametro_valor;
        }

        $response = Http::get($this::URL_API_MOODLE, $parametros);
        $response_body = $response->getBody();
        $resultado = $response_body->getContents();
        $resultado = json_decode($response_body);
        return $resultado;
    }
    /**
     * Consulta a categoria raiz do moodle utilizado pelo sistema para armazenar novas categorias e novos cursos
     */
    private function consultaCategoriaRaiz() {
        $parametros['criteria'] = [];
        // Caso tenha sido passado parametro de consulta os insere no array criteria necessário para o metodo core_course_get_categories
        $parametros['criteria'][0]['key'] = "idnumber";
        $parametros['criteria'][0]['value'] = self::ID_NUMBER_CATEGORIA_RAIZ;
        $resposta = $this->enviarRequisicaoMoodle('core_course_get_categories', $parametros);

        // Caso a Api do Moodle tenha retornado um erro
        if(isset($resposta->exception)){
            LogController::ErroAPIMoodle($resposta, __FUNCTION__);
            return false;
        }// Caso a categoria não exista chama a função que cria a categoria raiz
        else if($resposta == NULL || count($resposta) == 0){
            $resposta = $this->criarCategoriaRaiz();
            if($resposta){
                return $this->consultaCategoriaRaiz();
            }
        }
        return $resposta[0];
    }
    /**
     * Cadastra a categoria raiz que o sistema utilizará para cadastrar novas categorias e novos cursos
     */
    private function criarCategoriaRaiz() {
        $categoria = [];
        $categoria['name'] = "Categoria Skinner";
        $categoria['idnumber'] = self::ID_NUMBER_CATEGORIA_RAIZ;
        $parametros['categories'] = [$categoria];
        $resposta = $this->enviarRequisicaoMoodle('core_course_create_categories', $parametros);
        if(isset($resposta->exception)){
            LogController::ErroAPIMoodle($resposta, __FUNCTION__, (object) $categoria);
            return false;
        }else{
            LogController::SucessoCriarCategoria((object) $categoria);
            return $resposta;
        }
    }

    /**
     * Realiza a consulta das categorias cadastradas no moodle com ou sem parâmetros
     * @param string $chave Qual parâmetro de pesquisa será usado
     * @param string $valor Qual valor do parâmetro de pesquisa que será usado
     * @param bool $unico Informa se deve carregar apenas um registro ou todos
     * @return bool|object|array 
     */
    function consultaCategorias(string $chave = null, string $valor = null, bool $unico = false) {
        $parametros['criteria'] = [];
        // Caso tenha sido passado parametro de consulta os insere no array criteria necessário para o metodo core_course_get_categories
        if($chave != null && $valor != null){
            $parametros['criteria'][0]['key'] = $chave;
            $parametros['criteria'][0]['value'] = $valor;
        }
        $resposta = $this->enviarRequisicaoMoodle('core_course_get_categories', $parametros);

        // Caso a Api do Moodle tenha retornado um erro
        if(isset($resposta->exception)){
            LogController::ErroAPIMoodle($resposta, __FUNCTION__);
            return false;
        }// Caso o resultado não tenha sido o desejado retorna false
        else if($resposta == NULL || count($resposta) == 0){
            return false;
        }// Caso tenha sido realizado uma filtragem, realiza manualmente pois a api só filtra por ID 
        else if($unico && count($resposta)){
            return $resposta[0];
        }
        return $resposta;
    }

    /**
     * Cadastra as categorias no moodle
     * @param array $lista_categorias
     */
    function criarCategorias(array $lista_categorias) {
        if(count($lista_categorias)){
            foreach ($lista_categorias as $categoria) {
                $parametros['categories'] = [$categoria];
                $resposta = $this->enviarRequisicaoMoodle('core_course_create_categories', $parametros);
                if(isset($resposta->exception)){
                    LogController::ErroAPIMoodle($resposta, __FUNCTION__, (object) $categoria);
                }else{
                    LogController::SucessoCriarCategoria((object) $categoria);
                }
            }
        }
    }

    /**
     * Realiza a consulta dos coortes cadastrados no moodle com ou sem parâmetros
     * @param string $chave Qual parâmetro de pesquisa será usado
     * @param string $valor Qual valor do parâmetro de pesquisa que será usado
     * @param bool $unico Informa se deve carregar apenas um registro ou todos
     * @return bool|object|array 
     */
    function consultaCoortes(string $chave = null, string $valor = null, bool $unico = false) {
        $resposta = $this->enviarRequisicaoMoodle('core_cohort_get_cohorts');

        // Caso a Api do Moodle tenha retornado um erro
        if(isset($resposta->exception)){
            LogController::ErroAPIMoodle($resposta, __FUNCTION__);
            return false;
        }// Caso o resultado não tenha sido o desejado retorna false
        else if($resposta == NULL || count($resposta) == 0){
            return false;
        }// Caso tenha sido realizado uma filtragem, realiza manualmente pois a api só filtra por ID 
        else if($chave != null && $valor != null){
            $resposta_filtrada = [];
            foreach ($resposta as $coorte) {
                if($coorte->{$chave} == $valor){
                    array_push($resposta_filtrada, $coorte);
                }
            }
            if($unico && count($resposta_filtrada)){
                return $resposta_filtrada[0];
            }else{
                return $resposta_filtrada;
            }
        }
        return $resposta;
    }

    /**
     * Cadastra os coortes no moodle
     * @param array $lista_coortes
     */
    function criarCoortes(array $lista_coortes) {
        if(count($lista_coortes)){
            foreach ($lista_coortes as $coorte) {
                $parametros['cohorts'] = [$coorte];
                $resposta = $this->enviarRequisicaoMoodle('core_cohort_create_cohorts', $parametros);
                if(isset($resposta->exception)){
                    LogController::ErroAPIMoodle($resposta, __FUNCTION__, (object) $coorte);
                }else{
                    LogController::SucessoCriarCoorte((object) $coorte);
                }
            }
        }
    }

    /**
     * Realiza a consulta dos cursos cadastrados no moodle com ou sem parâmetros
     * @param string $chave Qual parâmetro de pesquisa será usado
     * @param string $valor Qual valor do parâmetro de pesquisa que será usado
     * @param bool $unico Informa se deve carregar apenas um registro ou todos
     * @return bool|object|array 
     */
    function consultaCursos(string $chave = null, string $valor = null, bool $unico = false) {
        $parametros['field'] = "";
        $parametros['value'] = "";
        // Caso tenha sido passado parametro de consulta os insere no array criteria necessário para o metodo core_course_get_categories
        if($chave != null && $valor != null){
            $parametros['field'] = $chave;
            $parametros['value'] = $valor;
        }
        $resposta = $this->enviarRequisicaoMoodle('core_course_get_courses_by_field', $parametros);

        // Caso a Api do Moodle tenha retornado um erro
        if(isset($resposta->exception)){
            LogController::ErroAPIMoodle($resposta, __FUNCTION__);
            return false;
        }// Caso o resultado não tenha sido o desejado retorna false
        else if($resposta == NULL || count($resposta->courses) == 0){
            return false;
        }else if($unico){
            return $resposta->courses[0];
        }
        return $resposta->courses;
    }

    /**
     * Cadastra os cursos no moodle
     * @param array $lista_cursos
     */
    function criarCursos(array $lista_cursos) {
        if(count($lista_cursos)){
            foreach ($lista_cursos as $curso) {
                $parametros['courses'] = [$curso];
                $resposta = $this->enviarRequisicaoMoodle('core_course_create_courses', $parametros);
                if(isset($resposta->exception)){
                    LogController::ErroAPIMoodle($resposta, __FUNCTION__, (object) $curso);
                }else{
                    LogController::SucessoCriarCurso((object) $curso);
                }
            }
        }
    }

    /**
     * Realiza a consulta dos grupos cadastrados no moodle pelo id do curso a que pertence e com ou sem parâmetros
     * @param int $id_curso Curso que os grupos pertencem
     * @param string $chave Qual parâmetro de pesquisa será usado
     * @param string $valor Qual valor do parâmetro de pesquisa que será usado
     * @param bool $unico Informa se deve carregar apenas um registro ou todos
     * @return bool|object|array 
     */
    function consultaGruposPorIdCurso(int $id_curso, string $chave = null, string $valor = null, bool $unico = false) {
        $parametros['courseid'] = $id_curso;
        $resposta = $this->enviarRequisicaoMoodle('core_group_get_course_groups', $parametros);
        // Caso a Api do Moodle tenha retornado um erro
        if(isset($resposta->exception)){
            LogController::ErroAPIMoodle($resposta, __FUNCTION__);
            return false;
        }// Caso o resultado não tenha sido o desejado retorna false
        else if($resposta == NULL || count($resposta) == 0){
            return false;
        }// Caso tenha sido realizado uma filtragem, realiza manualmente pois a api só filtra por ID 
        else if($chave != null && $valor != null){
            $resposta_filtrada = [];
            foreach ($resposta as $grupo) {
                if($grupo->{$chave} == $valor){
                    array_push($resposta_filtrada, $grupo);
                }
            }
            if($unico && count($resposta_filtrada)){
                return $resposta_filtrada[0];
            }else{
                return $resposta_filtrada;
            }
        }
        return $resposta;
    }
    /**
     * Realiza a consulta dos grupos cadastrados no moodle id do grupo
     * @param array $ids_grupo Array com os ids dos grupos a serem filtrados
     * @return array 
     */
    function consultaGruposPorIds(array $ids_grupo){
        $parametros['groupids'] = $ids_grupo;
        $resposta = $this->enviarRequisicaoMoodle('core_group_get_groups', $parametros);
        // Caso a Api do Moodle tenha retornado um erro
        if(isset($resposta->exception)){
            LogController::ErroAPIMoodle($resposta, __FUNCTION__);
            return false;
        }// Caso o resultado não tenha sido o desejado retorna false
        else if($resposta == NULL || count($resposta) == 0){
            return false;
        }
        return $resposta;
    }

    /**
     * Cadastra os grupos no moodle
     * @param array $lista_grupos
     */
    function criarGrupos(array $lista_grupos) {
        if(count($lista_grupos)){
            foreach ($lista_grupos as $grupo) {
                $parametros['groups'] = [$grupo];
                $resposta = $this->enviarRequisicaoMoodle('core_group_create_groups', $parametros);
                if(isset($resposta->exception)){
                    LogController::ErroAPIMoodle($resposta, __FUNCTION__, (object) $grupo);
                }else{
                    LogController::SucessoCriarGrupo((object) $grupo);
                }
            }
        }
    }

    /**
     * Realiza a consulta dos usuarios cadastrados no moodle com ou sem parâmetros
     * @param string $chave Qual parâmetro de pesquisa será usado Informa qual o parâmetro será utilizado na filtragem
     * @param string $valor Qual valor do parâmetro de pesquisa que será usado Informa qual o valor do parâmetro a ser filtrado
     * @param bool $unico Informa se deve carregar apenas um registro ou todos
     * @return bool|object|array 
     */
    function consultaUsuarios(string $chave = null, string $valor = null, bool $unico = false) {
        $parametros['criteria'] = [];
        // Caso tenha sido passado parametro de consulta os insere no array criteria necessário para o metodo core_course_get_categories
        if($chave != null && $valor != null){
            $parametros['criteria'][0]['key'] = $chave;
            $parametros['criteria'][0]['value'] = $valor;
        }
        $resposta = $this->enviarRequisicaoMoodle('core_user_get_users', $parametros);

        // Caso a Api do Moodle tenha retornado um erro
        if(isset($resposta->exception)){
            LogController::ErroAPIMoodle($resposta, __FUNCTION__);
            return false;
        }// Caso o resultado não tenha sido o desejado retorna false
        else if($resposta == NULL || count($resposta->users) == 0){
            return false;
        }else if($unico){
            return $resposta->users[0];
        }
        return $resposta->users;
    }

    /**
     * Cadastra os usuários no moodle
     * @param array $lista_usuarios
     * @return array
     */
    function criarUsuarios(array $lista_usuarios) : array {
        $lista_usuarios_Cadastrados = [];
        if(count($lista_usuarios)){
            foreach ($lista_usuarios as $usuario) {
                $parametros['users'] = [$usuario];
                $resposta = $this->enviarRequisicaoMoodle('core_user_create_users', $parametros);
                if(isset($resposta->exception)){
                    LogController::ErroAPIMoodle($resposta, __FUNCTION__, (object) $usuario);
                }else{
                    LogController::SucessoCriarUsuario((object) $usuario);
                    $usuario['id_usuario_moodle'] = $resposta[0]->id;
                    array_push($lista_usuarios_Cadastrados, (object) $usuario);
                }
            }
        }
        return $lista_usuarios_Cadastrados;
    }

    /**
     * Realiza a consulta dos usuarios vinculados a um curso no moodle
     * @param int $id_curso Id do curso a ser consultado
     * @param int $id_usuario Id do usuário a ser consultado
     * @return bool|object|array 
     */
    function consultaVinculosUsuariosCurso(int $id_curso, int $id_usuario = null) {
        $parametros['courseid'] = $id_curso;
        $resposta = $this->enviarRequisicaoMoodle('core_enrol_get_enrolled_users', $parametros);
        // Caso a Api do Moodle tenha retornado um erro
        if(isset($resposta->exception)){
            LogController::ErroAPIMoodle($resposta, __FUNCTION__);
            return false;
        }// Caso o resultado não tenha sido o desejado retorna false
        else if($resposta == NULL || count($resposta) == 0){
            return false;
        }// Caso tenha sido realizado uma filtragem, realiza manualmente pois a api só filtra por ID 
        else if($id_usuario != null){
            $contem_usuario = array_filter($resposta, function($vinculo) use($id_usuario) {
                return $vinculo->id == $id_usuario;
            });

            if($contem_usuario){
                return $contem_usuario;
            }else{
                return false;
            }
        }
        return $resposta;
    }

    /**
     * Vincula um usuário a um curso conforme os ids e papel
     */
    function criarVinculosUsuarioCurso(array $lista_vinculos) {
        if(count($lista_vinculos)){
            foreach ($lista_vinculos as $vin_usuario_curso) {
                $parametros['enrolments'] = [$vin_usuario_curso];
                $resposta = $this->enviarRequisicaoMoodle('enrol_manual_enrol_users', $parametros);
                // Carrega os id_numbers dos registros vinculados para armazenar o log
                $usuario = $this->consultaUsuarios('id', $vin_usuario_curso['userid']);
                $curso = $this->consultaCursos('id', $vin_usuario_curso['courseid']);
                $vin_usuario_curso['username'] = $usuario[0]->username;
                $vin_usuario_curso['idnumber_curso'] = $curso[0]->idnumber;
                if(isset($resposta->exception)){
                    LogController::ErroAPIMoodle($resposta, __FUNCTION__, (object) $vin_usuario_curso);
                }else{
                    LogController::SucessoCriarVinculoUsuarioCurso((object) $vin_usuario_curso);
                }
            }
        }
    }

    /**
     * Realiza a consulta dos usuarios vinculados a um grupo no moodle
     * @param int $id_grupo Id do grupo a ser consultado
     * @param int $id_usuario Id do usuário a ser consultado
     * @return bool|object|array 
     */
    function consultaVinculosUsuariosGrupo(int $id_grupo, int $id_usuario = null) {
        $parametros['groupids'] = [$id_grupo];
        $resposta = $this->enviarRequisicaoMoodle('core_group_get_group_members', $parametros);
        // Caso a Api do Moodle tenha retornado um erro
        if(isset($resposta->exception)){
            LogController::ErroAPIMoodle($resposta, __FUNCTION__);
            return false;
        }// Caso o resultado não tenha sido o desejado retorna false
        else if($resposta == NULL || count($resposta) == 0){
            return false;
        }
        $resposta = $resposta[0];
        if ($id_usuario) {
            $contem_usuario = array_filter($resposta->userids, function($user_id) use($id_usuario) {
                return $user_id == $id_usuario;
            });
            if($contem_usuario){
                $resposta->userids = $contem_usuario;
            }else{
                $resposta = false;
            }
        }
        return $resposta;
    }

    /**
     * Vincula um usuário a um grupo
     */
    function criarVinculosUsuarioGrupo(array $lista_vinculos) {
        if(count($lista_vinculos)){
            foreach ($lista_vinculos as $vin_usuario_grupo) {
                $parametros['members'] = [$vin_usuario_grupo];
                $resposta = $this->enviarRequisicaoMoodle('core_group_add_group_members', $parametros);
                // Carrega os id_numbers dos registros vinculados para armazenar o log
                $usuario = $this->consultaUsuarios('id', $vin_usuario_grupo['userid']);
                $grupo = $this->consultaGruposPorIds([$vin_usuario_grupo['groupid']]);
                $vin_usuario_grupo['username'] = $usuario[0]->username;
                $vin_usuario_grupo['idnumber_grupo'] = $grupo[0]->idnumber;
                if(isset($resposta->exception)){
                    LogController::ErroAPIMoodle($resposta, __FUNCTION__, (object) $vin_usuario_grupo);
                }else{
                    LogController::SucessoCriarVinculoUsuarioGrupo((object) $vin_usuario_grupo);
                }
            }
        }
    }

    /**
     * Realiza a consulta dos usuarios vinculados a um coorte no moodle
     * @param int $id_coorte Id do coorte a ser consultado
     * @param int $id_usuario Id do usuário a ser consultado
     * @return bool|object|array 
     */
    function consultaVinculosUsuariosCoorte(int $id_coorte, int $id_usuario = null) {
        $parametros['cohortids'] = [$id_coorte];
        $resposta = $this->enviarRequisicaoMoodle('core_cohort_get_cohort_members', $parametros);
        // Caso a Api do Moodle tenha retornado um erro
        if(isset($resposta->exception)){
            LogController::ErroAPIMoodle($resposta, __FUNCTION__);
            return false;
        }// Caso o resultado não tenha sido o desejado retorna false
        else if($resposta == NULL || count($resposta) == 0){
            return false;
        }
        $resposta = $resposta[0];

        if ($id_usuario) {
            $contem_usuario = array_filter($resposta->userids, function($user_id) use($id_usuario) {
                return $user_id == $id_usuario;
            });
            if($contem_usuario){
                $resposta->userids = $contem_usuario;
            }else{
                $resposta = false;
            }
        }
        return $resposta;
    }

    /**
     * Vincula um usuário a um coorte
     */
    function criarVinculosUsuarioCoorte(array $lista_vinculos) {
        if(count($lista_vinculos)){
            foreach ($lista_vinculos as $vin_usuario_coorte) {
                $parametros['members'] = [$vin_usuario_coorte];
                $resposta = $this->enviarRequisicaoMoodle('core_cohort_add_cohort_members', $parametros);
                // Retorno pode ser warning ou exception
                if(isset($resposta->exception)){
                    LogController::ErroAPIMoodle($resposta, __FUNCTION__, (object) $vin_usuario_coorte);
                }else if(isset($resposta->warnings) && count($resposta->warnings)){
                    LogController::ErroAPIMoodle($resposta->warnings[0], __FUNCTION__, (object) $vin_usuario_coorte);
                }else{
                    LogController::SucessoCriarVinculoUsuarioCoorte((object) $vin_usuario_coorte);
                }
            }
        }
    }
}
