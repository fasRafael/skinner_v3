<?php

namespace App\Http\Controllers;

class MoodleController extends Controller
{
    #region Constantes de acesso ao moodle
    /*const URL_API_MOODLE = 'https://ava.faculdadesensu.edu.br/webservice/restjson/server.php';
    const TOKEN_API_MOODLE = "7535f43e6ea21e1ce02f6a0ed8dbd565";*/
    const URL_API_MOODLE = 'http://10.1.1.6/moodle/webservice/restjson/server.php';
    const TOKEN_API_MOODLE = "81fd3d3e002d205db936c1d0cf7db3a9";
    const ID_NUMBER_CATEGORIA_RAIZ = "SKINNER_V3";
    #endregion

    /**
     * Realiza a requisição para o webservice do moodle conforme a função informada via parâmetro e as constantes de acesso ao moodle
     * @param string $funcao Responsável por informar ao webservice moodle qual método será executado
     * @param array $lista_parametros array composto pelas variáveis que serão passadas ao método do webservice do moodle
     */
    function enviarRequisicaoMoodle(string $funcao, array $lista_parametros = []) {
        $parametros['wstoken'] = $this::TOKEN_API_MOODLE;
        $parametros['wsfunction'] = $funcao;
        foreach ($lista_parametros as $parametro_chave => $parametro_valor) {
            $parametros[$parametro_chave] = $parametro_valor;
        }

        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, self::URL_API_MOODLE);
        curl_setopt($ch, CURLOPT_POST, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parametros));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $resultado = json_decode(curl_exec($ch));
        return $resultado;
    }
    /**
     * Consulta a categoria raiz do moodle utilizado pelo sistema para armazenar novas categorias e novos cursos
     */
    function consultaCategoriaRaiz() {
        $parametros['criteria'] = [];
        // Caso tenha sido passado parametro de consulta os insere no array criteria necessário para o metodo core_course_get_categories
        $parametros['criteria'][0]['key'] = "idnumber";
        $parametros['criteria'][0]['value'] = self::ID_NUMBER_CATEGORIA_RAIZ;
        $resposta = $this->enviarRequisicaoMoodle('core_course_get_categories', $parametros);

        // Caso a categoria não exista chama a função que cria a categoria raiz
        if($resposta == NULL || count($resposta) == 0){
            $resposta = $this->criaCategoriaRaiz();
            if($resposta){
                return $this->consultaCategoriaRaiz();
            }
        }// Caso o resultado não tenha sido o desejado retorna false
        else if(isset($resposta->exception)){
            return false;
        }else{
            return $resposta[0];
        }
    }

    /**
     * Realiza a consulta das categorias cadastradas no moodle com ou sem parâmetros
     * @param string $chave
     * @param string $valor
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

        // Caso o resultado não tenha sido o desejado retorna false
        if($resposta == NULL || isset($resposta->exception) || count($resposta) == 0){
            $resposta = false;
        }// Caso tenha sido realizado uma filtragem, realiza manualmente pois a api só filtra por ID 
        else if($unico && count($resposta)){
            $resposta = $resposta[0];
        }
        return $resposta;
    }

    /**
     * Cadastra a categoria raiz que o sistema utilizará para cadastrar novas categorias e novos cursos
     */
    function criaCategoriaRaiz() {
        $categoria = [];
        $categoria['name'] = "Categoria Skinner";
        $categoria['idnumber'] = self::ID_NUMBER_CATEGORIA_RAIZ;
        $parametros['categories'] = [$categoria];
        $resposta = $this->enviarRequisicaoMoodle('core_course_create_categories', $parametros);
        return $resposta;
    }

    /**
     * Cadastra as categorias no moodle
     * @param array $lista_categorias
     */
    function criaCategorias(array $lista_categorias) {
        if(count($lista_categorias)){
            $parametros['categories'] = $lista_categorias;
            $resposta = $this->enviarRequisicaoMoodle('core_course_create_categories', $parametros);
            return $resposta;
        }else{
            return false;
        }
    }

    /**
     * Realiza a consulta dos coortes cadastrados no moodle com ou sem parâmetros
     * @param string $chave
     * @param string $valor
     * @param bool $unico Informa se deve carregar apenas um registro ou todos
     * @return bool|object|array 
     */
    function consultaCoortes(string $chave = null, string $valor = null, bool $unico = false) {
        $resposta = $this->enviarRequisicaoMoodle('core_cohort_get_cohorts');

        // Caso o resultado não tenha sido o desejado retorna false
        if($resposta == NULL || isset($resposta->exception) || count($resposta) == 0){
            $resposta = false;
        }// Caso tenha sido realizado uma filtragem, realiza manualmente pois a api só filtra por ID 
        else if($chave != null && $valor != null){
            $resposta_filtrada = [];
            foreach ($resposta as $coorte) {
                if($coorte->{$chave} == $valor){
                    array_push($resposta_filtrada, $coorte);
                }
            }
            if($unico && count($resposta_filtrada)){
                $resposta = $resposta_filtrada[0];
            }else{
                $resposta = $resposta_filtrada;
            }
        }
        return $resposta;
    }

    /**
     * Cadastra os coortes no moodle
     * @param array $lista_coortes
     */
    function criaCoortes(array $lista_coortes) {
        if(count($lista_coortes)){
            $parametros['cohorts'] = $lista_coortes;
            $resposta = $this->enviarRequisicaoMoodle('core_cohort_create_cohorts', $parametros);
            return $resposta;
        }else{
            return false;
        }
    }

    /**
     * Realiza a consulta dos cursos cadastrados no moodle com ou sem parâmetros
     * @param string $chave
     * @param string $valor
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

        // Caso o resultado não tenha sido o desejado retorna false
        if($resposta == NULL || isset($resposta->exception) || count($resposta->courses) == 0 || count($resposta->warnings)){
            $resposta = false;
        }else if($unico){
            $resposta = $resposta->courses[0];
        }else{
            $resposta = $resposta->courses;
        }
        return $resposta;
    }

    /**
     * Cadastra os cursos no moodle
     * @param array $lista_cursos
     */
    function criaCursos(array $lista_cursos) {
        if(count($lista_cursos)){
            $parametros['courses'] = $lista_cursos;
            $resposta = $this->enviarRequisicaoMoodle('core_course_create_courses', $parametros);
            return $resposta;
        }else{
            return false;
        }
    }

    /**
     * Realiza a consulta dos grupos cadastrados no moodle com ou sem parâmetros
     * @param string $chave
     * @param string $valor
     * @param bool $unico Informa se deve carregar apenas um registro ou todos
     * @return bool|object|array 
     */
    function consultaGrupos(string $chave = null, string $valor = null, bool $unico = false) {
        $parametros['groupids'] = [];
        $resposta = $this->enviarRequisicaoMoodle('core_group_get_groups', $parametros);

        // Caso o resultado não tenha sido o desejado retorna false
        if($resposta == NULL || isset($resposta->exception) || count($resposta) == 0 || count($resposta->warnings)){
            $resposta = false;
        }// Caso tenha sido realizado uma filtragem, realiza manualmente pois a api só filtra por ID 
        else if($chave != null && $valor != null){
            $resposta_filtrada = [];
            foreach ($resposta as $grupo) {
                if($grupo->{$chave} == $valor){
                    array_push($resposta_filtrada, $grupo);
                }
            }
            if($unico && count($resposta_filtrada)){
                $resposta = $resposta_filtrada[0];
            }else{
                $resposta = $resposta_filtrada;
            }
        }
        return $resposta;
    }

    /**
     * Cadastra os grupos no moodle
     * @param array $lista_grupos
     */
    function criaGrupos(array $lista_grupos) {
        if(count($lista_grupos)){
            $parametros['groups'] = $lista_grupos;
            $resposta = $this->enviarRequisicaoMoodle('core_group_create_groups', $parametros);
            return $resposta;
        }else{
            return false;
        }
    }

    /**
     * Realiza a consulta dos usuarios cadastrados no moodle com ou sem parâmetros
     * @param string $chave Informa qual o parâmetro será utilizado na filtragem
     * @param string $valor Informa qual o valor do parâmetro a ser filtrado
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

        // Caso o resultado não tenha sido o desejado retorna false
        if($resposta == NULL || isset($resposta->exception) || count($resposta->users) == 0){
            $resposta = false;
        }else if($unico){
            $resposta = $resposta->users[0];
        }else{
            $resposta = $resposta->users;
        }
        return $resposta;
    }

    /**
     * Cadastra os usuários no moodle
     * @param array $lista_usuarios
     */
    function criaUsuarios(array $lista_usuarios) {
        if(count($lista_usuarios)){
            $parametros['users'] = $lista_usuarios;
            $resposta = $this->enviarRequisicaoMoodle('core_user_create_users', $parametros);
            return $resposta;
        }else{
            return false;
        }
    }

    /**
     * Vincula um usuário a um curso conforme os ids e papel
     */
    function criaVinculosUsuarioCurso(array $lista_vinculos) {
        if(count($lista_vinculos)){
            $parametros['enrolments'] = $lista_vinculos;
            $resposta = $this->enviarRequisicaoMoodle('enrol_manual_enrol_users', $parametros);
            return $resposta;
        }else{
            return false;
        }
    }
}
