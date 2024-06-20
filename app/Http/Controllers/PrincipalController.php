<?php

namespace App\Http\Controllers;

class PrincipalController extends Controller
{
    /** Período igual a 1 dia. Utilizado para cadastros de curso, tuma, módulo e aluno. */
    const HORAS_CADASTRO = 240;
    /** Período igual a 1 ano. Utilizado para vincular cursos, turmas ou módulos a alunos. */
    const HORAS_VINCULO = 8760;
    /** Período igual a 10 anos. Utilizado para vincular usuários a alunos. */
    const HORAS_PERIODO_COMPLETO = 87600;
    /** Representa o ano referente a quais turmas, cursos, disciplinas e alunos serão processados. */
    const ANO = 2024;
    /** Representa o semestre referente a quais turmas, cursos, disciplinas e alunos serão processados. */
    const SEMESTRE = 2;

    function principal(){
        LogController::InicioExecucao();
        $seiController = new SEIController();
        $moodleController = new MoodleController();

        #region Atualiza usuários
        $lista_usuarios_sei = $seiController->listarPessoas_v2($this::HORAS_CADASTRO);
        $lista_usuarios_atualizados = $this->preparaListaUsuariosAtualizadosMoodle($lista_usuarios_sei, $moodleController);
        $moodleController->atualizarUsuarios($lista_usuarios_atualizados);
        #endregion

        #region Cadastro de Curso|Turma|Modulo
        //Consulta a lista de Cursos, Turmas e Modulos do SEI onde as Turmas correspondem ao ANO/SEMESTRE
        /** @var array $lista_ctm_sei Equivalente a lista de objetos Curso|Turma|Modulo do SEI */
        $lista_ctm_sei = $seiController->listarCursos_v2($this::HORAS_CADASTRO);
        $lista_ctm_sei = array_filter($lista_ctm_sei, function($ctm){
            return str_contains($ctm->turma->turma_nome, $this::ANO . '/' . $this::SEMESTRE);
        });

        if(count($lista_ctm_sei)){
            #region Cadastra categorias
            $lista_categorias = $this->preparaListaCategoriasMoodle($lista_ctm_sei, $moodleController);
            $moodleController->criarCategorias($lista_categorias);
            unset($lista_categorias);
            #endregion
            #region Cadastra cursos
            $lista_cursos = $this->preparaListaCursosMoodle($lista_ctm_sei, $moodleController);
            $moodleController->criarCursos($lista_cursos);
            unset($lista_cursos); 
            #endregion
            #region Cadastra coortes
            $lista_coortes = $this->preparaListaCoortesMoodle($lista_ctm_sei, $moodleController);
            $moodleController->criarCoortes($lista_coortes);
            unset($lista_coortes);
            #endregion
            #region Cadastra grupos
            $lista_grupos = $this->preparaListaGruposMoodle($lista_ctm_sei, $moodleController);
            $moodleController->criarGrupos($lista_grupos);
            unset($lista_grupos);
            #endregion
        }
        unset($lista_ctm_sei);
        #endregion

        #region Cadastro de Aluno, Professor e Coordenador e cria seus vínculos 
        $lista_vin_alunos_sei = $seiController->listarVinculoAlunos_v2($this::HORAS_CADASTRO);
        $lista_vin_professores_sei = $seiController->listarVinculoProfessores_v2($this::HORAS_CADASTRO);
        $lista_vin_coordenadores_sei = $seiController->listarVinculoCoordenadores_v2($this::HORAS_CADASTRO);

        #region Unifica os dados de vínculo de Aluno, Professor e Coordenador em uma única lista
        $lista_vinculos_sei = $this->preparaListaVinculosSei($lista_vin_alunos_sei, $lista_vin_professores_sei, $lista_vin_coordenadores_sei, $seiController);
        unset($lista_vin_alunos_sei);
        unset($lista_vin_professores_sei);
        unset($lista_vin_coordenadores_sei);
        #endregion

        if(count($lista_vinculos_sei)){
            #region Cadastra usuários
            $lista_usuarios_novos = $this->preparaListaUsuariosMoodle($lista_vinculos_sei, $moodleController);
            $moodleController->criarUsuarios($lista_usuarios_novos);
            unset($lista_usuarios_novos);
            #endregion

            $lista_vinculos = $this->preparaListaVinculosComDadosUsuarioMoodle($lista_vinculos_sei, $moodleController);
            unset($lista_vinculos_sei);

            #region Cadastra vinculos de usuários com Categorias Curso, Coorte e Grupo
            $lista_vin_usuario_curso_moodle = [];
            $lista_vin_usuario_grupo_moodle = [];
            $lista_vin_usuario_coorte_moodle = [];
            // Cadastra os novos vinculos no moodle
            foreach ($lista_vinculos as $vinculo) {
                // Verifica se a Categoria a que esse usuário será vinculado já existe no Moodle
                $categoria_moodle = $moodleController->consultaCategorias('idnumber', $vinculo->curso_codigo, 1);
                if(!$categoria_moodle){
                    // Cria-se a categoria dentro da categoria raiz
                    $lista_categorias = $this->preparaListaCategoriasMoodle([$vinculo->ctm_sei], $moodleController);
                    $moodleController->criarCategorias($lista_categorias);
                    $categoria_moodle = $moodleController->consultaCategorias('idnumber', $vinculo->curso_codigo, 1);
                    unset($lista_categorias);
                }

                // Verifica se o Curso a que esse usuário será vinculado já existe no Moodle
                $curso_moodle = $moodleController->consultaCursos('idnumber', $vinculo->modulo_codigo, 1);
                if(!$curso_moodle){
                    // Cria-se o curso dentro da categoria raiz
                    $lista_cursos = $this->preparaListaCursosMoodle([$vinculo->ctm_sei], $moodleController);
                    $moodleController->criarCursos($lista_cursos);
                    $curso_moodle = $moodleController->consultaCursos('idnumber', $vinculo->modulo_codigo, 1);
                    unset($lista_cursos);
                }

                #region Monta lista de vinculos do usuário ao curso
                // Independente se o vinculo já existe ou não o cadastro deve ser realizado novamente caso contrário não vai atualizar dados de timestart e timeend.
                $vin_usuario_curso_moodle = [];
                $vin_usuario_curso_moodle['roleid'] = ($vinculo->tipo_vinculo == 'Aluno')?'5':'3';
                $vin_usuario_curso_moodle['userid'] = $vinculo->id_usuario_moodle;
                $vin_usuario_curso_moodle['courseid'] = $curso_moodle->id;
                $vin_usuario_curso_moodle['timestart'] = substr($vinculo->ctm_sei->modulo->modulo_inicio, 0, -3);
                $vin_usuario_curso_moodle['timeend'] = substr($vinculo->ctm_sei->modulo->modulo_fim, 0, -3);
                array_push($lista_vin_usuario_curso_moodle, $vin_usuario_curso_moodle);
                unset($vin_usuario_curso_moodle);
                #endregion

                // Verifica se o Coorte a que esse usuário será vinculado já existe no Moodle
                $coorte_moodle = $moodleController->consultaCoortes('idnumber', 'turma - ' . $vinculo->turma_codigo, 1);
                if(!$coorte_moodle){
                    /* Se o coorte não existe no moodle é possível que ele tenha sido manipulado ou excluído. Cria-se um novo. */
                    $lista_coortes = $this->preparaListaCoortesMoodle([$vinculo->ctm_sei], $moodleController);
                    $moodleController->criarCoortes($lista_coortes);
                    $coorte_moodle = $moodleController->consultaCoortes('idnumber', 'turma - ' . $vinculo->turma_codigo, 1);
                    unset($lista_coortes);
                }
                #region Monta lista de vinculos do usuário ao coorte
                if(!$moodleController->consultaVinculosUsuariosCoorte($coorte_moodle->id, $vinculo->id_usuario_moodle)){
                    $vin_usuario_coorte_moodle = [];
                    $vin_usuario_coorte_moodle['cohorttype'] = (object)['type' => 'idnumber', 'value' => $coorte_moodle->idnumber];
                    $vin_usuario_coorte_moodle['usertype'] = (object)['type' => 'username', 'value' => $vinculo->username_moodle];
                    array_push($lista_vin_usuario_coorte_moodle, $vin_usuario_coorte_moodle);
                    unset($vin_usuario_coorte_moodle);
                }
                #endregion

                // Verifica se o Grupo a que esse usuário será vinculado já existe no Moodle
                $grupo_moodle = $moodleController->consultaGruposPorIdCurso($curso_moodle->id, 'idnumber', $vinculo->turma_codigo . ' - ' . $vinculo->modulo_codigo, 1);
                if(!$grupo_moodle){
                    /* Se o grupo não existe no moodle é possível que ele tenha sido manipulado ou excluído. Cria-se um novo. */
                    $lista_grupos = $this->preparaListaGruposMoodle([$vinculo->ctm_sei], $moodleController);
                    $moodleController->criarGrupos($lista_grupos);
                    $grupo_moodle = $moodleController->consultaGruposPorIdCurso($curso_moodle->id, 'idnumber', $vinculo->turma_codigo . ' - ' . $vinculo->modulo_codigo, 1);
                    unset($lista_grupos);
                }
                #region Monta lista de vinculos do usuário ao grupo
                if(!$moodleController->consultaVinculosUsuariosGrupo($grupo_moodle->id, $vinculo->id_usuario_moodle)){
                    $vin_usuario_grupo_moodle = [];
                    $vin_usuario_grupo_moodle['groupid'] = $grupo_moodle->id;
                    $vin_usuario_grupo_moodle['userid'] = $vinculo->id_usuario_moodle;
                    array_push($lista_vin_usuario_grupo_moodle, $vin_usuario_grupo_moodle);
                    unset($vin_usuario_grupo_moodle);
                }
                #endregion

                unset($grupo_moodle);
                unset($curso_moodle);
            }
            unset($vinculo);

            $lista_vin_usuario_curso_moodle = array_unique($lista_vin_usuario_curso_moodle, SORT_REGULAR);
            $moodleController->criarVinculosUsuarioCurso($lista_vin_usuario_curso_moodle);
            unset($lista_vin_usuario_curso_moodle);

            $lista_vin_usuario_grupo_moodle = array_unique($lista_vin_usuario_grupo_moodle, SORT_REGULAR);
            $moodleController->criarVinculosUsuarioGrupo($lista_vin_usuario_grupo_moodle);
            unset($lista_vin_usuario_grupo_moodle);

            $lista_vin_usuario_coorte_moodle = array_unique($lista_vin_usuario_coorte_moodle, SORT_REGULAR);
            $moodleController->criarVinculosUsuarioCoorte($lista_vin_usuario_coorte_moodle);
            unset($lista_vin_usuario_coorte_moodle);
            #endregion
        }
        #endregion
        LogController::FimExecucao();
    }

    /**
     * Converte os cursos da lista de Curso|Turma|Módulo do SEI em uma lista de Categorias no formato que o Moodle precisa
     * @param array $lista_ctm_sei
     * @param MoodleController $moodleController
     * @return array $lista_categorias
     */
    function preparaListaCategoriasMoodle(array $lista_ctm_sei, MoodleController $moodleController) : array {
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
            if(!$moodleController->consultaCategorias('idnumber', $curso_sei->curso_codigo, 1)){
                $categoria = [];
                $categoria['name'] = trim($curso_sei->curso_nome);
                $categoria['idnumber'] = $curso_sei->curso_codigo;
                $categoria['parent'] = $moodleController->categoria_raiz->id;
                array_push($lista_categorias, $categoria);
            }
        }
        unset($curso_sei);
        unset($categoria);
        return $lista_categorias;
    }

    /**
     * Converte os módulos da lista de Curso|Turma|Módulo do SEI em uma lista de Cursos no formato que o Moodle precisa
     * @param array $lista_ctm_sei
     * @param MoodleController $moodleController
     * @return array $lista_cursos
     */
    function preparaListaCursosMoodle(array $lista_ctm_sei, MoodleController $moodleController) : array {
        // Filtra da lista de modulos a serem convertidos em cursos e remove duplicados
        $lista_modulos_sei = [];
        foreach ($lista_ctm_sei as $ctm_sei) {
            $modulo = $ctm_sei->modulo;
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
            if(!$moodleController->consultaCursos('idnumber', $modulo_sei->modulo_codigo, 1)){
                $nome_breve = $this->geraNomeBreveCurso($modulo_sei->modulo_nome, $modulo_sei->modulo_codigo);
                // Obs.: Datas não são cadastradas pois a data de início e fim da disciplinas variam conforme a turma 
                $curso = [];
                $curso['categoryid'] = $moodleController->categoria_raiz->id;
                $curso['fullname'] = trim($modulo_sei->modulo_nome);
                $curso['shortname'] = $nome_breve;
                $curso['idnumber'] = $modulo_sei->modulo_codigo;
                $curso['lang'] = 'pt_br';
                array_push($lista_cursos, $curso);
            }
        }
        unset($modulo_sei);
        unset($nome_breve);
        unset($curso);
        unset($lista_modulos_sei);
        return $lista_cursos;
    }

    /**
     * Converte as turmas da lista de Curso|Turma|Módulo do SEI em uma lista de Grupos no formato que o Moodle precisa
     * @param array $lista_ctm_sei
     * @param MoodleController $moodleController
     * @return array $lista_grupos
     */
    function preparaListaGruposMoodle(array $lista_ctm_sei, MoodleController $moodleController) : array {
        $lista_turmas_sei = $this->formataListaTurmasSei($lista_ctm_sei);

        // Cria a lista de grupos no formato que o moodle exige apenas com as turmas ainda não cadastradas como grupos no Moodle
        $lista_grupos = [];
        foreach ($lista_turmas_sei as $turma_sei) {
            // Verifica se a turma ainda não existe no moodle como um grupo pelo idnumber
            $curso_moodle = $moodleController->consultaCursos('idnumber', $turma_sei->modulo_codigo, 1);
            if(!$moodleController->consultaGruposPorIdCurso($curso_moodle->id, 'idnumber', $turma_sei->grupo_codigo, 1)){
                $grupo = [];
                $grupo['courseid'] = $curso_moodle->id;
                $grupo['name'] = trim($turma_sei->turma_nome);
                $grupo['idnumber'] = $turma_sei->grupo_codigo;
                $grupo['description'] = 'Essa é a descrição do grupo referente ao vinculo da turma: "' . $turma_sei->turma_nome . '" com o curso: "' . $curso_moodle->fullname . '".' ;
                array_push($lista_grupos, $grupo);
            }
        }
        $lista_grupos = array_unique($lista_grupos, SORT_REGULAR);
        unset($turma_sei);
        unset($curso_moodle);
        unset($grupo);
        unset($lista_turmas_sei);
        return $lista_grupos;
    }

    /**
     * Converte as turmas da lista de Curso|Turma|Módulo do SEI em uma lista de Coortes no formato que o Moodle precisa
     * @param array $lista_ctm_sei
     * @param MoodleController $moodleController
     * @return array $lista_coortes
     */
    function preparaListaCoortesMoodle(array $lista_ctm_sei, MoodleController $moodleController) : array {
        $lista_turmas_sei = $this->formataListaTurmasSei($lista_ctm_sei);
        
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
                // Ao cadastrar um coorte verifica se a Categoria a quem pertence existe
                if(!$moodleController->consultaCategorias('idnumber', $turma_sei->curso_codigo)){
                    // FAZER ESSA OPERAÇÃO COM UMA FUNÇÃO INDEPENDENTE
                    $seiController = new SEIController();
                    $lista_ctm_curso_sei = $seiController->listarCursos_v2($this::HORAS_VINCULO, 0, $turma_sei->curso_codigo);
                    $lista_ctm_curso_sei = array_filter($lista_ctm_curso_sei, function($ctm){
                        return str_contains($ctm->turma->turma_nome, $this::ANO . '/' . $this::SEMESTRE);
                    });
                    $lista_categorias = $this->preparaListaCategoriasMoodle($lista_ctm_curso_sei, $moodleController);
                    $moodleController->criarCategorias($lista_categorias);
                    unset($seiController);
                    unset($lista_ctm_curso_sei);
                    unset($lista_categorias);
                }
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
        return $lista_coortes;
    }

    /**
     * Converte os usuarios da lista de vinculos do SEI em uma lista de Usuários no formato que o moodle precisa
     * @param array $lista_vin_sei
     * @param MoodleController $moodleController
     * @return array $lista_usuarios_novos
     */
    function preparaListaUsuariosMoodle(array $lista_vin_sei, MoodleController $moodleController) : array {
        $lista_usuarios_novos = [];
        foreach ($lista_vin_sei as $vin_sei) {
            // Caso o usuário desse vínculo ainda não tenha sido inserido na lista
            if(!array_filter($lista_usuarios_novos, function ($usuario_novo) use ($vin_sei){return $usuario_novo['username'] == $vin_sei->cpf;}, 1)){
                // Verifica se o aluno não tem usuário no moodle
                if(!$moodleController->consultaUsuarios('idnumber', $vin_sei->pessoa_sei->codigo, 1)){
                    // Verifica se o email informado é válido
                    if(!filter_var($vin_sei->pessoa_sei->email, FILTER_VALIDATE_EMAIL)){
                        LogController::ErroCriarUsuarioMoodleEmailInvalido($vin_sei->pessoa_sei);
                        break 1;
                    }
                    $usuario = [];
                    $usuario['username'] = $vin_sei->cpf;
                    $usuario['password'] = $vin_sei->cpf;
                    $usuario['idnumber'] = $vin_sei->pessoa_sei->codigo;
                    $usuario['firstname'] = explode(" ", trim($vin_sei->pessoa_sei->nome), 2)[0];
                    $usuario['lastname'] = explode(" ", trim($vin_sei->pessoa_sei->nome), 2)[1];
                    $usuario['email'] = strtolower(trim($vin_sei->pessoa_sei->email));
                    $usuario['customfields'] = [];
                    array_push($usuario['customfields'], ["type" => "CPF", "value" => $this->formataCPF($vin_sei->cpf)]);
                    array_push($usuario['customfields'], ["type" => "token", "value" => $vin_sei->pessoa_sei->token]);
                    $usuario['calendartype'] = 'gregorian';
                    $usuario['timezone'] = 'America/Sao_Paulo';
                    $usuario['lang'] = 'pt_br';

                    array_push($lista_usuarios_novos, $usuario);
                    unset($usuario);
                }
            }
        }
        return $lista_usuarios_novos;
    }

    /**
     * Filtra da lista de pessoas do SEI os usuários que ainda não estão suspensos no moodle e verifica se algum campo está diferente com seu valor no moodle e prepara uma lista de usuários e campos atualizados no formato que o moodle precisa.
     * Campos Atualizados:
     * - customfields.CPF
     * - customfields.token
     * - username
     * - firstname
     * - lastname
     * - email
     * @param array $lista_pessoas_sei
     * @param MoodleController $moodleController
     * @return array $lista_usuarios_atualizados
     */
    function preparaListaUsuariosAtualizadosMoodle(array $lista_pessoas_sei, MoodleController $moodleController) : array {
        $lista_usuarios_atualizados = [];
        foreach ($lista_pessoas_sei as $usuario_sei) {
            $usuario_moodle = $moodleController->consultaUsuarios('idnumber', $usuario_sei->codigo, 1);
            if($usuario_moodle && !$usuario_moodle->suspended){
                $atualizado = false;
                $usuario = [];
                $usuario['id'] = $usuario_moodle->id;
                if(isset($usuario_moodle->customfields)){
                    $cpf_existe = false;
                    $cpf_alterado = false;
                    $token_existe = false;
                    $token_alterado = false;
                    foreach ($usuario_moodle->customfields as $customfield) {
                        if($customfield->shortname == "CPF"){
                            $cpf_existe = true;
                            if($customfield->value != $this->formataCPF($usuario_sei->cpf)){
                                $cpf_alterado = true;
                            }
                        }else if($customfield->shortname == "token"){
                            $token_existe = true;
                            if($customfield->value != $usuario_sei->token){
                                $token_alterado = true;
                            }
                        }
                    }
                    if(!$cpf_existe || $cpf_alterado){
                        $atualizado = true;
                        $usuario['customfields'] = [];
                        array_push($usuario['customfields'], ["type" => "CPF", "value" => $this->formataCPF($usuario_sei->cpf)]);
                    }
                    if(!$token_existe || $token_alterado){
                        $atualizado = true;
                        $usuario['customfields'] = [];
                        array_push($usuario['customfields'], ["type" => "token", "value" => $usuario_sei->token]);
                    }
                }else{
                    $atualizado = true;
                    $usuario['customfields'] = [];
                    array_push($usuario['customfields'], ["type" => "CPF", "value" => $this->formataCPF($usuario_sei->cpf)]);
                    array_push($usuario['customfields'], ["type" => "token", "value" => $usuario_sei->token]);
                }
                if ($usuario_sei->cpf != $usuario_moodle->username) {
                    $atualizado = true;
                    $usuario['username'] = $usuario_sei->cpf;
                }
                if ($usuario_sei->nome != $usuario_moodle->fullname) {
                    $atualizado = true;
                    $usuario['firstname'] = explode(" ", trim($usuario_sei->nome), 2)[0];
                    $usuario['lastname'] = explode(" ", trim($usuario_sei->nome), 2)[1];
                }
                if ($usuario_sei->email != $usuario_moodle->email) {
                    $atualizado = true;
                    $usuario['email'] = $usuario_sei->email;
                }
                
                if($atualizado){
                    array_push($lista_usuarios_atualizados, $usuario);
                }
            }
        }
        return $lista_usuarios_atualizados;
    }

    function preparaListaVinculosUsuarioCursoMoodle(){
    }

    function preparaListaVinculosUsuarioCoorteMoodle(){
    }

    function preparaListaVinculosUsuarioGrupoMoodle(){
    }

    /**
     * Unifica as listas de vínculos do SEI (alunos, professores ou coordenadores), filtra aqueles que estão ativos e estão vinculados a uma Turma correspondente ao ANO/SEMESTRES e une ao objeto filtrado os dados de Curso|Turma|Módulo e de Usuário.
     * @param array $lista_alunos a lista de vínculos de alunos do SEI
     * @param array $lista_professores a lista de vínculos de professores do SEI
     * @param array $lista_coordenadores a lista de vínculos de coordenadores do SEI
     * @param SEIController $seiController classe do SEI para consulta de cursos e pessoas conforme constantes HORAS_VINCULO e HORAS_PERIODO_COMPLETO 
     * @return array
     */
    function preparaListaVinculosSei(array $lista_alunos, array $lista_professores, array $lista_coordenadores, SEIController $seiController) : array {
        $lista_vinculos_filtrados = [];
        $lista_ctm = $seiController->listarCursos_v2($this::HORAS_VINCULO);
        $lista_pessoas = $seiController->listarPessoas_v2($this::HORAS_PERIODO_COMPLETO);

        $lista_alunos = array_filter($lista_alunos, function($aluno) {
            return $aluno->vinculo_situacao_nome == "Ativa";
        });
        
        foreach ($lista_alunos as $aluno) {
            $aluno->tipo_vinculo = "Aluno";
        }
        foreach ($lista_professores as $professor) {
            $professor->tipo_vinculo = "Professor";
        }
        foreach ($lista_coordenadores as $coordenador) {
            $coordenador->tipo_vinculo = "Coordenador";
        }

        $lista_vinculos = array_merge($lista_alunos, $lista_professores, $lista_coordenadores);
        foreach ($lista_vinculos as $vinculo) {
            $ctm_sei = array_filter($lista_ctm, function($ctm_sei) use ($vinculo){
                return ($vinculo->curso_codigo == $ctm_sei->curso->curso_codigo && $vinculo->turma_codigo == $ctm_sei->turma->turma_codigo && $vinculo->modulo_codigo == $ctm_sei->modulo->modulo_codigo) && str_contains($ctm_sei->turma->turma_nome, $this::ANO . '/' . $this::SEMESTRE);
            });
            if(count($ctm_sei)){
                $vinculo->ctm_sei = array_pop($ctm_sei);
                $pessoa_sei = array_filter($lista_pessoas, function($pessoa_sei) use ($vinculo){
                    return ($pessoa_sei->cpf == $vinculo->cpf);
                });
                $vinculo->pessoa_sei = array_pop($pessoa_sei);
                array_push($lista_vinculos_filtrados, $vinculo);
            }
        }
        return $lista_vinculos_filtrados;
    }

    /**
     * Consulta os dados de Usuário do Moodle da lista de vínculos do SEI, aqueles que não tiverem usuários são removidos da lista.
     * @param array $lista_vinculos_sei a lista de vínculos do SEI, já deve estar formatada e preenchida com alunos, professores e coordenadores
     * @param MoodleController $moodleController classe do Moodle para consulta de usuários
     * @return array
     */
    function preparaListaVinculosComDadosUsuarioMoodle(array $lista_vinculos_sei, MoodleController $moodleController) : array {
        // Filtra na lista de vínculos aqueles que tem um usuário no moodle
        $lista_vinculos = [];
        foreach ($lista_vinculos_sei as $vinculo_sei) {
            // Consulta o usuário no moodle correspondente ao usuário no SEI
            $usuario_moodle = $moodleController->consultaUsuarios('idnumber', $vinculo_sei->pessoa_sei->codigo, 1);
            if(!$usuario_moodle){
                // Caso tenha ocorrido algum erro no cadastro do usuário, como Email inválido, o sistema não pode prosseguir com o cadastro do usuário
                LogController::ErroCriarVinculoMoodleUsuarioNaoExiste($vinculo_sei->pessoa_sei);
                break 1;
            }else{
                $vinculo_sei->id_usuario_moodle = $usuario_moodle->id;
                $vinculo_sei->username_moodle = $usuario_moodle->username;
                array_push($lista_vinculos, $vinculo_sei);
            }
        }
        return $lista_vinculos;
    }

    /**
     * Converte a lista de Curso|Turma|Modulo para um formato com apenas os dados necesários para a turma
     * @param array $lista_ctm_sei
     * @return array $lista_turmas_sei
     */
    function formataListaTurmasSei(array $lista_ctm_sei) : array {
        // Filtra da lista de turmas a serem convertidas em grupos, para cada vínculo entre turma e módulo cria um código de grupo diferente
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
        return $lista_turmas_sei;
    }

    /**
     * Através do nome e código do módulo o nome breve que será utilizado no cadastro do curso no moodle
     * @param string $nome_modulo
     * @param string $modulo_codigo
     * @return string $nome_breve
     */
    function geraNomeBreveCurso(string $nome_modulo, int $modulo_codigo) : string {
        $nome_modulo = trim($nome_modulo);
        $nome_modulo = preg_replace('/[áàãâä]/ui', 'a', $nome_modulo);
        $nome_modulo = preg_replace('/[éèêë]/ui', 'e', $nome_modulo);
        $nome_modulo = preg_replace('/[íìîï]/ui', 'i', $nome_modulo);
        $nome_modulo = preg_replace('/[óòõôö]/ui', 'o', $nome_modulo);
        $nome_modulo = preg_replace('/[úùûü]/ui', 'u', $nome_modulo);
        $nome_modulo = preg_replace('/[ç]/ui', 'c', $nome_modulo);
        $nome_modulo = preg_replace('/[,(),;:|!"#$%&\/=?~^><ªº–-]/', ' ', $nome_modulo);
        $nome_modulo = preg_replace('/ e /ui', ' ', $nome_modulo);
        $nome_modulo = preg_replace('/ a /ui', ' ', $nome_modulo);
        $nome_modulo = preg_replace('/ o /ui', ' ', $nome_modulo);
        $nome_modulo = preg_replace('/ ao /ui', ' ', $nome_modulo);
        $nome_modulo = preg_replace('/ da /ui', ' ', $nome_modulo);
        $nome_modulo = preg_replace('/ de /ui', ' ', $nome_modulo);
        $nome_modulo = preg_replace('/ do /ui', ' ', $nome_modulo);
        $nome_modulo = strtoupper($nome_modulo);
        $array_nome = explode(' ', $nome_modulo);

        $nome_breve = "";
        foreach ($array_nome as $caracter) {
            if($caracter == "II" || $caracter == "III" || $caracter == "IV" || $caracter == "VI" || $caracter == "VII" || $caracter == "VIII" || $caracter == "IX"){
                $nome_breve .= $caracter;
            }else if(isset($caracter[0])){
                $nome_breve .= $caracter[0];
            }
        }

        return $nome_breve . "_" . $modulo_codigo;
    }

    /**
     * Formata uma string numérica com 11 dígitos para o formato de CPF
     * // ???VERIFICAR RETORNOS DE EXCEÇÕES
     * @param string $cpf
     * @return string $cpf
     */
    function formataCPF(string $cpf) : string {
        // Remove caracteres não numéricos
        $cpf = preg_replace("/\D/", '', $cpf);
        // Divide a string em conjuntos de 3 e 2 números e intercala . e - entre esses conjuntos
        return preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "\$1.\$2.\$3-\$4", $cpf);
    }
}
