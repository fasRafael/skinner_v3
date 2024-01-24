<?php

namespace App\Http\Controllers;

class PrincipalController extends Controller
{
    const HORAS_CADASTRO = 24; // 1 dia
    const HORAS_VINCULO = 4380; // 6 meses
    const ANO = 2024;
    const SEMESTRE = 1;

    function principal(){
        
        $seiController = new SEIController();
        $moodleController = new MoodleController();
        /*$seiController->listarPessoas($this::HORAS_VINCULO);
        $seiController->listarPessoas_v2($this::HORAS_VINCULO);
        $seiController->listarCursos($this::HORAS_CADASTRO);
        $seiController->listarCursos_v2($this::HORAS_CADASTRO);
        $seiController->vinculacoesAlunos($this::HORAS_CADASTRO);
        $seiController->vinculacoesAlunos_v2($this::HORAS_CADASTRO);
        $seiController->vinculacoesProfessores($this::HORAS_CADASTRO);
        $seiController->vinculacoesProfessores_v2($this::HORAS_CADASTRO);
        $seiController->vinculacoesCoordenadores($this::HORAS_CADASTRO);
        $seiController->vinculacoesCoordenadores_v2($this::HORAS_CADASTRO);
        $seiController->materiaisDisponibilizados(10000000);
        $seiController->downloadMaterial(26058321241);
        $seiController->materiaisExcluidos(10000000);*/

        $categoria_raiz = $moodleController->consultaCategoriaRaiz();

        #region Consulta a lista de Cursos, Turmas e Modulos do SEI onde as Turmas correspondem ao ANO/SEMESTRE
        /** @var array $lista_ctm_sei Equivalente a lista de objetos Curso/Turma/Modulo do SEI */
        $lista_ctm_sei = $seiController->listarCursos_v2($this::HORAS_CADASTRO);
        $lista_ctm_sei = array_filter($lista_ctm_sei, function($ctm){
            return str_contains($ctm->turma->turma_nome, $this::ANO . '/' . $this::SEMESTRE);
        });
        #endregion
        
        if(count($lista_ctm_sei) > 0){
            #region Cadastra categorias
            // Filtra da lista de cursos a serem convertidos em categorias
            $lista_cursos_sei = [];
            foreach ($lista_ctm_sei as $ctm_sei) {
                array_push($lista_cursos_sei, $ctm_sei->curso);
            }
            unset($ctm_sei);
            // Remove duplicatas
            $lista_cursos_sei = array_unique($lista_cursos_sei, SORT_REGULAR);

            // Cria a lista de categorias no formato que o moodle exige apenas com os cursos ainda não cadastrados como categoria no Moodle
            $lista_categorias = [];
            foreach ($lista_cursos_sei as $curso_sei) {
                // Verifica se o curso ainda não existe no moodle como categoria pelo idnumber
                if(!$moodleController->consultaCategorias('idnumber', $curso_sei->curso_codigo)){
                    $categoria = [];
                    $categoria['name'] = trim($curso_sei->curso_nome);
                    $categoria['idnumber'] = $curso_sei->curso_codigo;
                    $categoria['parent'] = $categoria_raiz->id;
                    array_push($lista_categorias, $categoria);
                }
            }
            unset($curso_sei);
            unset($categoria);

            $moodleController->criaCategorias($lista_categorias);
            unset($lista_categorias);
            
            #region Cadastra coortes de categorias
            $lista_coortes = [];
            foreach ($lista_cursos_sei as $curso_sei) {
                if(!$moodleController->consultaCoortes('idnumber', 'curso - ' . $curso_sei->curso_codigo)){
                    // VERIFICAR SE CATEGORIA EXISTE ANTES DE VINCULAR O COORTE?
                    $categorytype = (object)['type' => 'idnumber', 'value' => $curso_sei->curso_codigo];

                    $coorte = [];
                    $coorte['categorytype'] = $categorytype;
                    $coorte['name'] = trim($curso_sei->curso_nome);
                    $coorte['idnumber'] = 'curso - ' . $curso_sei->curso_codigo;
                    array_push($lista_coortes, $coorte);
                }
            }
            unset($curso_sei);
            unset($categorytype);
            unset($coorte);
            unset($lista_cursos_sei);

            $moodleController->criaCoortes($lista_coortes);
            unset($lista_coortes);

            #endregion
            #endregion

            #region Cadastra cursos
            // Filtra da lista de modulos a serem convertidos em cursos e remove duplicados, para código de curso utilizará o do primeiro registro
            $lista_modulos_sei = [];
            foreach ($lista_ctm_sei as $ctm_sei) {
                $modulo = $ctm_sei->modulo;
                $modulo->curso_codigo = $ctm_sei->curso->curso_codigo;
                $modulo_ja_adicionado = false;
                foreach ($lista_modulos_sei as $modulo_aux) {
                    if($modulo->modulo_codigo == $modulo_aux->modulo_codigo){
                        $modulo_ja_adicionado = true;
                        break;
                    }
                }
                if(!$modulo_ja_adicionado){
                    array_push($lista_modulos_sei, $modulo);
                }
            }
            unset($ctm_sei);
            unset($modulo);
            unset($modulo_ja_adicionado);
            unset($modulo_aux);

            // Cria a lista de cursos no formato que o moodle exige apenas com os modulos ainda não cadastrados como cursos no Moodle
            $lista_cursos = [];
            foreach ($lista_modulos_sei as $modulo_sei) {
                // Verifica se o modulo ainda não existe no moodle como curso pelo idnumber
                if(!$moodleController->consultaCursos('idnumber', $modulo_sei->modulo_codigo)){
                    $nome_breve = $this->geraNomeBreveCurso($modulo_sei->modulo_nome, $modulo_sei->modulo_codigo);
                    /* Ao invés de cadastrar no primeiro curso cadastra em uma categoria padrão: A Categoria Skinner */
                    //$categorias = $moodleController->consultaCategorias('idnumber', $modulo_sei->curso_codigo);
                    // Só cadastra o curso se a categoria a quem ele pertence existir
                    //if($categorias[0]){
                        $curso = [];
                        // Obs.: Datas não estão sendo cadastradas pois a data de início e fim da disciplinas podem variar conforme a turma 
                        //$curso['categoryid'] = $categorias[0]->id;
                        $curso['categoryid'] = $categoria_raiz->id;
                        $curso['fullname'] = trim($modulo_sei->modulo_nome);
                        $curso['shortname'] = $nome_breve;
                        $curso['idnumber'] = $modulo_sei->modulo_codigo;
                        $curso['lang'] = 'pt_br';
                        array_push($lista_cursos, $curso);
                    //}
                }
            }
            unset($modulo_sei);
            //unset($categorias);
            unset($nome_breve);
            unset($curso);
            unset($lista_modulos_sei);

            $moodleController->criaCursos($lista_cursos);
            unset($lista_cursos);
            #endregion

            #region Cadastra grupos
            // Filtra da lista de turmas a serem convertidas em grupos e remove duplicados, para código do módulo utilizará o do primeiro registro
            /*$lista_turmas_sei = [];
            foreach ($lista_ctm_sei as $ctm_sei) {
                $turma = $ctm_sei->turma;
                $turma->modulo_codigo = $ctm_sei->modulo->modulo_codigo;
                $turma_ja_adicionado = false;
                foreach ($lista_turmas_sei as $turma_aux) {
                    if($turma->turma_codigo == $turma_aux->turma_codigo){
                        $turma_ja_adicionado = true;
                        break;
                    }
                }
                if(!$turma_ja_adicionado){
                    array_push($lista_turmas_sei, $turma);
                }
            }
            unset($ctm_sei);
            unset($turma);
            unset($turma_ja_adicionado);
            unset($turma_aux);*/
            // ALTERNATIVA um grupo será equivalente ao vinculo de uma turma com um módulo
            // Filtra da lista de turmas a serem convertidas em grupos, para cada vinculo de turma com módulo cria um código de grupo diferente
            $lista_turmas_sei = [];
            foreach ($lista_ctm_sei as $ctm_sei) {
                $turma = $ctm_sei->turma;
                $turma->curso_codigo = $ctm_sei->curso->curso_codigo;
                $turma->modulo_codigo = $ctm_sei->modulo->modulo_codigo;
                $turma->grupo_codigo = $turma->turma_codigo . ' - ' . $turma->modulo_codigo;
                array_push($lista_turmas_sei, $turma);
            }
            unset($ctm_sei);
            unset($turma);

            // Cria a lista de grupos no formato que o moodle exige apenas com as turmas ainda não cadastradas como grupos no Moodle
            $lista_grupos = [];
            foreach ($lista_turmas_sei as $turma_sei) {
                // Verifica se a turma ainda não existe no moodle como um grupo pelo idnumber
                if(!$moodleController->consultaGrupos('idnumber', $turma_sei->grupo_codigo)){
                    $cursos = $moodleController->consultaCursos('idnumber', $turma_sei->modulo_codigo);

                    $grupo = [];
                    $grupo['courseid'] = $cursos[0]->id;
                    $grupo['name'] = trim($turma_sei->turma_nome);
                    $grupo['idnumber'] = $turma_sei->grupo_codigo;
                    $grupo['description'] = 'Essa é a descrição do grupo referente ao vinculo da turma: "' . $turma_sei->turma_nome . '" com o curso: "' . $cursos[0]->fullname . '".' ;
                    array_push($lista_grupos, $grupo);
                }
            }
            unset($turma_sei);
            unset($cursos);
            unset($grupo);

            $moodleController->criaGrupos($lista_grupos);
            unset($lista_grupos);
                        
            #region Cadastra coortes de grupos
            // Remove da lista de turmas os vinculos de módulo utilizado em grupo, deixando apenas os dados da turma
            foreach ($lista_turmas_sei as $turma_sei) {
                unset($turma_sei->modulo_codigo);
                unset($turma_sei->grupo_codigo);
            }
            unset($turma_sei);
            // Remove duplicatas
            $lista_turmas_sei = array_unique($lista_turmas_sei, SORT_REGULAR);

            $lista_coortes = [];
            foreach ($lista_turmas_sei as $turma_sei) {
                if(!$moodleController->consultaCoortes('idnumber', 'turma - ' . $turma_sei->turma_codigo)){
                    // VERIFICAR COMPORTAMENTO EM CASO DE TURMA AGRUPADA
                    // VERIFICAR SE CATEGORIA EXISTE ANTES DE VINCULAR O COORTE?
                    $categorytype = (object)['type' => 'idnumber', 'value' => $turma_sei->curso_codigo];

                    $coorte = [];
                    $coorte['categorytype'] = $categorytype;
                    $coorte['name'] = trim($turma_sei->turma_nome);
                    $coorte['idnumber'] = 'turma - ' . $turma_sei->turma_codigo;
                    array_push($lista_coortes, $coorte);
                }
            }
            unset($turma_sei);
            unset($categorytype);
            unset($coorte);
            unset($lista_turmas_sei);

            $moodleController->criaCoortes($lista_coortes);
            unset($lista_coortes);
            #endregion
            #endregion
        }
        
        #region Atualiza usuários
       /* $lista_usuarios_sei = $seiController->listarPessoas_v2($this::HORAS_CADASTRO);
        foreach ($lista_usuarios_sei as $usuario_sei) {
            $resposta = $moodleController->consultaUsuarios('username', $usuario_sei->cpf);
            // REVISAR LÓGICA
            if($resposta){
                $usuario_moodle = $resposta[0];
                if(!$usuario_moodle->suspended){
                    foreach ($usuario_moodle->customfields as $customfield) {
                        $usuario_moodle->{$customfield->shortname} = $customfield->value;
                    }
                    if(!isset($usuario_moodle->cpf)){}
                    if(!isset($usuario_moodle->token)){}
                    if($usuario_sei->cpf != $usuario_moodle->username ||
                       $usuario_sei->cpf != $usuario_moodle->cpf ||
                       $usuario_sei->nome != $usuario_moodle->fullname ||
                       $usuario_sei->email != $usuario_moodle->email ||
                       $usuario_sei->token != $usuario_moodle->token){
                        
                    }
                }
            }
        }*/
        #endregion

        #region Criar Aluno
        #endregion
    }

    /**
     * Através do nome e código do módulo o nome breve que será utilizado no cadastro do curso no moodle
     * @param string $nome_modulo
     * @param string $codigo_modulo
     */
    function geraNomeBreveCurso(string $nome_modulo, int $codigo_modulo){
        $nome_modulo = trim($nome_modulo);
        $nome_modulo = preg_replace('/[áàãâä]/ui', 'a', $nome_modulo);
        $nome_modulo = preg_replace('/[éèêë]/ui', 'e', $nome_modulo);
        $nome_modulo = preg_replace('/[íìîï]/ui', 'i', $nome_modulo);
        $nome_modulo = preg_replace('/[óòõôö]/ui', 'o', $nome_modulo);
        $nome_modulo = preg_replace('/[úùûü]/ui', 'u', $nome_modulo);
        $nome_modulo = preg_replace('/[ç]/ui', 'c', $nome_modulo);
        $nome_modulo = preg_replace('/[,(),;:|!"#$%&\/=?~^><ªº–-]/', '', $nome_modulo);
        $nome_modulo = strtoupper($nome_modulo);
        $array_nome = explode(' ', $nome_modulo);

        $nome_breve = "";
        foreach ($array_nome as $caracter) {
            if(isset($caracter[0])){
                $nome_breve .= $caracter[0];
            }
        }

        return $nome_breve . $codigo_modulo;
    }
}
