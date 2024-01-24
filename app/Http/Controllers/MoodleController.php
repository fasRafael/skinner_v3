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
     */
    function consultaCategorias(string $chave = null, string $valor = null) {
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
        }// Caso tenha sido realizado uma filtragem, filtra o valor novamente pois a filtragem da api aceita valores que contanham os dados filtrados 
        else if($chave != null && $valor != null){
            $resposta_filtrada = [];
            foreach ($resposta as $categoria) {
                if($categoria->{$chave} == $valor){
                    array_push($resposta_filtrada, $categoria);
                }
            }
            $resposta = $resposta_filtrada;
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
        if(count($lista_categorias) > 0){
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
     */
    function consultaCoortes(string $chave = null, string $valor = null) {
        $resposta = $this->enviarRequisicaoMoodle('core_cohort_get_cohorts');

        // Caso o resultado não tenha sido o desejado retorna false
        if($resposta == NULL || isset($resposta->exception) || count($resposta) == 0){
            $resposta = false;
        }// Caso tenha sido realizado uma filtragem, realiza a filtragem pois a api não tem uma função simplificada para isso 
        else if($chave != null && $valor != null){
            $resposta_filtrada = [];
            foreach ($resposta as $coorte) {
                if($coorte->{$chave} == $valor){
                    array_push($resposta_filtrada, $coorte);
                }
            }
            $resposta = $resposta_filtrada;
        }
        return $resposta;
    }

    /**
     * Cadastra os coortes no moodle
     * @param array $lista_coortes
     */
    function criaCoortes(array $lista_coortes) {
        if(count($lista_coortes) > 0){
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
     */
    function consultaCursos(string $chave = null, string $valor = null) {
        $parametros['field'] = "";
        $parametros['value'] = "";
        // Caso tenha sido passado parametro de consulta os insere no array criteria necessário para o metodo core_course_get_categories
        if($chave != null && $valor != null){
            $parametros['field'] = $chave;
            $parametros['value'] = $valor;
        }
        $resposta = $this->enviarRequisicaoMoodle('core_course_get_courses_by_field', $parametros);

        // Caso o resultado não tenha sido o desejado retorna false
        if($resposta == NULL || isset($resposta->exception) || count($resposta->courses) == 0 || count($resposta->warnings) > 0){
            $resposta = false;
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
        if(count($lista_cursos) > 0){
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
     */
    function consultaGrupos(string $chave = null, string $valor = null) {
        $parametros['groupids'] = [];
        $resposta = $this->enviarRequisicaoMoodle('core_group_get_groups', $parametros);

        // Caso o resultado não tenha sido o desejado retorna false
        if($resposta == NULL || isset($resposta->exception) || count($resposta) == 0 || count($resposta->warnings) > 0){
            $resposta = false;
        }// Caso tenha sido realizado uma filtragem, realiza a filtragem pois a api não tem uma função simplificada para isso 
        else if($chave != null && $valor != null){
            $resposta_filtrada = [];
            foreach ($resposta as $grupo) {
                if($grupo->{$chave} == $valor){
                    array_push($resposta_filtrada, $grupo);
                }
            }
            $resposta = $resposta_filtrada;
        }
        return $resposta;
    }

    /**
     * Cadastra os grupos no moodle
     * @param array $lista_grupos
     */
    function criaGrupos(array $lista_grupos) {
        if(count($lista_grupos) > 0){
            $parametros['groups'] = $lista_grupos;
            $resposta = $this->enviarRequisicaoMoodle('core_group_create_groups', $parametros);
            return $resposta;
        }else{
            return false;
        }
    }

    /**
     * Realiza a consulta dos usuarios cadastrados no moodle com ou sem parâmetros
     * @param string $chave
     * @param string $valor
     */
    function consultaUsuarios(string $chave = null, string $valor = null) {
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
        }else{
            $resposta = $resposta->users;
        }
        return $resposta;
    }

}
