<?php

namespace App\Http\Controllers;

class PrincipalController extends Controller
{
    /** Período igual a 1 dia. Utilizado para cadastros de curso, tuma, módulo e aluno. */
    const HORAS_CADASTRO = 24;
    /** Período igual a 1 ano. Utilizado para vincular cursos, turmas ou módulos a alunos. */
    const HORAS_VINCULO = 8760;
    /** Período igual a 10 anos. Utilizado para vincular usuários a alunos. */
    const HORAS_PERIODO_COMPLETO = 87600;
    /** Representa o ano referente a quais turmas, cursos, disciplinas e alunos serão processados. */
    const ANO = 2024;
    /** Representa o semestre referente a quais turmas, cursos, disciplinas e alunos serão processados. */
    const SEMESTRE = 1;

    function principal(){
        LogController::InicioExecucao();
        $seiController = new SEIController();
        $moodleController = new MoodleController();
        #region Consulta a lista de Cursos, Turmas e Modulos do SEI onde as Turmas correspondem ao ANO/SEMESTRE
        /** @var array $lista_ctm_sei Equivalente a lista de objetos Curso/Turma/Modulo do SEI */
        $lista_ctm_sei = $seiController->listarCursos_v2($this::HORAS_CADASTRO);
        $lista_ctm_sei = array_filter($lista_ctm_sei, function($ctm){
            return str_contains($ctm->turma->turma_nome, $this::ANO . '/' . $this::SEMESTRE);
        });
        #endregion
        
        #region Cadastro de Curso/Turma/Modulo
        if(count($lista_ctm_sei) > 0){
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
        #endregion
        
        // ???ATUALIZAR USUÁRIOS

        $this->vincularAlunos();

        // ??? ATUALIZAR VINCULOS DATAS DE VINCULOS ALTERADAS

        #region Atualiza usuários
        /*
        $lista_usuarios_sei = $seiController->listarPessoas_v2($this::HORAS_CADASTRO);
        foreach ($lista_usuarios_sei as $usuario_sei) {
            $resposta = $moodleController->consultaUsuarios('username', $usuario_sei->cpf);
            // REVISAR LÓGICA
            if($resposta){
                $usuario_moodle = $resposta[0]; 
                if(!$usuario_moodle->suspended){
                    foreach ($usuario_moodle->customfields as $customfield) {
                        $usuario_moodle->{$customfield->shortname} = $customfield->value;
                    }
                    if(!isset($usuario_moodle->cpf)){
                        $usuario_moodle->cpf = $usuario_sei->cpf;
                    }
                    if(!isset($usuario_moodle->token)){
                        $usuario_moodle->token = $usuario_sei->token;
                    }
                    if($usuario_sei->cpf != $usuario_moodle->username ||
                       $usuario_sei->cpf != $usuario_moodle->cpf ||
                       $usuario_sei->nome != $usuario_moodle->fullname ||
                       $usuario_sei->email != $usuario_moodle->email ||
                       $usuario_sei->token != $usuario_moodle->token){
                        $usuario_moodle->username = $usuario_sei->cpf;
                        $usuario_moodle->cpf = $usuario_sei->cpf;
                        $usuario_moodle->fullname = $usuario_sei->fullname;
                        $usuario_moodle->email = $usuario_sei->email;
                        $usuario_moodle->token = $usuario_sei->token;
                    }
                }
            }
        }*/
        #endregion
        LogController::FimExecucao();
    }

    function vincularAlunos() {
        $seiController = new SEIController();
        $moodleController = new MoodleController();

        #region Criar Aluno
        // Filtra da lista de alunos os com situação Ativo
        $lista_vin_alunos_sei = $seiController->listarVinculoAlunos_v2($this::HORAS_CADASTRO);
        $lista_vin_alunos_sei = array_filter($lista_vin_alunos_sei, function($vin_aluno_sei){
            return $vin_aluno_sei->vinculo_situacao_nome == "Ativa";
        });

        // Filtra da lista de alunos os que foram vinculados a turmas equivalentes ao ano e semestre
        $lista_ctm_sei = $seiController->listarCursos_v2($this::HORAS_VINCULO);
        $lista_ctm_sei = array_filter($lista_ctm_sei, function($ctm){
            return str_contains($ctm->turma->turma_nome, $this::ANO . '/' . $this::SEMESTRE);
        });
        $lista_vin_alunos_aux = []; 
        foreach ($lista_vin_alunos_sei as $vin_aluno_sei) {
            foreach ($lista_ctm_sei as $ctm_sei) {
                if($vin_aluno_sei->turma_codigo == $ctm_sei->turma->turma_codigo){
                    array_push($lista_vin_alunos_aux, $vin_aluno_sei);
                    break;
                }
            }
            unset($ctm_sei);
        }
        $lista_vin_alunos_sei = $lista_vin_alunos_aux;
        unset($lista_vin_alunos_aux);
        unset($vin_aluno_sei);

        // Consulta lista de pessoas no sei para relacionamento posterior entre Vinculo Aluno e Usuário do SEI
        $lista_pessoas_sei = $seiController->listarPessoas_v2($this::HORAS_PERIODO_COMPLETO);

        // Cria os usuários que ainda não tem cadastro no moodle
        $lista_usuarios_novos = $this->preparaListaUsuariosMoodle($lista_vin_alunos_sei, $lista_pessoas_sei, $moodleController);
        $moodleController->criarUsuarios($lista_usuarios_novos);
        unset($lista_usuarios_novos);

        $lista_vin_usuario_curso_moodle = [];
        $lista_vin_usuario_grupo_moodle = [];
        $lista_vin_usuario_coorte_moodle = [];
        // Cadastra os novos vinculos no moodle
        foreach ($lista_vin_alunos_sei as $vin_aluno_sei) {
            // Consulta a Categoria/Curso/Turma correspondente ao vinculo do aluno no SEI 
            $ctm_sei = array_values(array_filter($lista_ctm_sei, function($ctm_sei) use($vin_aluno_sei) {
                return $ctm_sei->curso->curso_codigo == $vin_aluno_sei->curso_codigo && $ctm_sei->turma->turma_codigo == $vin_aluno_sei->turma_codigo && $ctm_sei->modulo->modulo_codigo == $vin_aluno_sei->modulo_codigo;
            }))[0];

            // Consulta o usuário correspondente ao vinculo do aluno no SEI
            $pessoa_sei = array_filter($lista_pessoas_sei, function($pessoa_sei) use($vin_aluno_sei) {
                return $pessoa_sei->cpf == $vin_aluno_sei->cpf;
            });
            $pessoa_sei = array_values($pessoa_sei)[0];

            // Consulta o usuário no moodle correspondente ao usuário no SEI
            $usuario_moodle = $moodleController->consultaUsuarios('idnumber', $pessoa_sei->codigo, 1);
            if(!$usuario_moodle){
                // Caso tenha ocorrido algum erro no cadastro do usuário, como Email inválido, o sistema não pode prosseguir com o cadastro do aluno
                LogController::ErroCriarVinculoMoodleUsuarioNaoExiste($pessoa_sei);
                break 1;
            }

            // Verifica se a Categoria a que esse aluno será vinculado já existe no Moodle
            $categoria_moodle = $moodleController->consultaCategorias('idnumber', $vin_aluno_sei->curso_codigo, 1);
            if(!$categoria_moodle){
                // Cria-se a categoria dentro da categoria raiz
                $lista_categorias = $this->preparaListaCategoriasMoodle([$ctm_sei], $moodleController);
                $moodleController->criarCategorias($lista_categorias);
                $categoria_moodle = $moodleController->consultaCategorias('idnumber', $vin_aluno_sei->curso_codigo, 1);
                unset($lista_categorias);
            }

            // Verifica se o Curso a que esse aluno será vinculado já existe no Moodle
            $curso_moodle = $moodleController->consultaCursos('idnumber', $vin_aluno_sei->modulo_codigo, 1);
            if(!$curso_moodle){
                // Cria-se o curso dentro da categoria raiz
                $lista_cursos = $this->preparaListaCursosMoodle([$ctm_sei], $moodleController);
                $moodleController->criarCursos($lista_cursos);
                $curso_moodle = $moodleController->consultaCursos('idnumber', $vin_aluno_sei->modulo_codigo, 1);
                unset($lista_cursos);
            }

            #region Monta lista de vinculos do usuário ao curso
            if(!$moodleController->consultaVinculosUsuariosCurso($curso_moodle->id, $usuario_moodle->id)){
                $vin_usuario_curso_moodle = [];
                $vin_usuario_curso_moodle['roleid'] = '5';
                $vin_usuario_curso_moodle['userid'] = $usuario_moodle->id;
                $vin_usuario_curso_moodle['courseid'] = $curso_moodle->id;
                $vin_usuario_curso_moodle['timestart'] = $ctm_sei->modulo->modulo_inicio;
                $vin_usuario_curso_moodle['timeend'] = $ctm_sei->modulo->modulo_fim;    
                array_push($lista_vin_usuario_curso_moodle, $vin_usuario_curso_moodle);
                unset($vin_usuario_curso_moodle);
            }
            #endregion

            // Verifica se o Coorte a que esse usuário será vinculado já existe no Moodle
            $coorte_moodle = $moodleController->consultaCoortes('idnumber', 'turma - ' . $vin_aluno_sei->turma_codigo, 1);
            if(!$coorte_moodle){
                /* Se o coorte não existe no moodle é possível que ele tenha sido manipulado ou excluído. Cria-se um novo. */
                $lista_coortes = $this->preparaListaCoortesMoodle([$ctm_sei], $moodleController);
                $moodleController->criarCoortes($lista_coortes);
                $coorte_moodle = $moodleController->consultaCoortes('idnumber', 'turma - ' . $vin_aluno_sei->turma_codigo, 1);
                unset($lista_coortes);
            }
            // Monta lista de vinculos do usuário ao coorte
            if(!$moodleController->consultaVinculosUsuariosCoorte($coorte_moodle->id, $usuario_moodle->id)){
                $vin_usuario_coorte_moodle = [];
                $vin_usuario_coorte_moodle['cohorttype'] = (object)['type' => 'id', 'value' => $coorte_moodle->id];
                $vin_usuario_coorte_moodle['usertype'] = (object)['type' => 'id', 'value' => $usuario_moodle->id];
                array_push($lista_vin_usuario_coorte_moodle, $vin_usuario_coorte_moodle);
                unset($vin_usuario_coorte_moodle);
            }

            // Verifica se o Grupo a que esse aluno será vinculado já existe no Moodle
            $grupo_moodle = $moodleController->consultaGruposPorIdCurso($curso_moodle->id, 'idnumber', $vin_aluno_sei->turma_codigo . ' - ' . $vin_aluno_sei->modulo_codigo, 1);
            if(!$grupo_moodle){
                /* Se o grupo não existe no moodle é possível que ele tenha sido manipulado ou excluído. Cria-se um novo. */
                $lista_grupos = $this->preparaListaGruposMoodle([$ctm_sei], $moodleController);
                $moodleController->criarGrupos($lista_grupos);
                $grupo_moodle = $moodleController->consultaGruposPorIdCurso($curso_moodle->id, 'idnumber', $vin_aluno_sei->turma_codigo . ' - ' . $vin_aluno_sei->modulo_codigo, 1);
                unset($lista_grupos);
            }
            #region Monta lista de vinculos do usuário ao grupo
            if(!$moodleController->consultaVinculosUsuariosGrupo($grupo_moodle->id, $usuario_moodle->id)){
                $vin_usuario_grupo_moodle = [];
                $vin_usuario_grupo_moodle['groupid'] = $grupo_moodle->id;
                $vin_usuario_grupo_moodle['userid'] = $usuario_moodle->id;
                array_push($lista_vin_usuario_grupo_moodle, $vin_usuario_grupo_moodle);
                unset($vin_usuario_grupo_moodle);
            }
            #endregion

            unset($grupo_moodle);
            unset($curso_moodle);
            unset($usuario_moodle);
            unset($ctm_sei);
        }
        unset($vin_aluno_sei);

        $moodleController->criarVinculosUsuarioCurso($lista_vin_usuario_curso_moodle);
        unset($lista_vin_usuario_curso_moodle);

        $moodleController->criarVinculosUsuarioGrupo($lista_vin_usuario_grupo_moodle);
        unset($lista_vin_usuario_grupo_moodle);

        // Remove duplicatas
        // TALVEZ TENHA UMA FORMA DE FAZER ESSA FILTRAGEM DURANTE A EXECUÇÃO
        $lista_vin_usuario_coorte_moodle = array_unique($lista_vin_usuario_coorte_moodle, SORT_REGULAR);
        $moodleController->criarVinculosUsuarioCoorte($lista_vin_usuario_coorte_moodle);
        unset($lista_vin_usuario_coorte_moodle);
        #endregion
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
     * Converte os usuarios da lista de Pessoas do SEI em uma lista de Usuários no formato que o moodle precisa
     * @param array $lista_vin_alunos_sei
     * @param array $lista_pessoas_sei
     * @param MoodleController $moodleController
     * @return array $lista_usuarios_novos
     */
    function preparaListaUsuariosMoodle(array $lista_vin_alunos_sei, array $lista_pessoas_sei, MoodleController $moodleController) : array {
        // Filtra da lista de vinculos de alunos os cpf's a serem convertidos em usuários
        $lista_cpf_alunos = [];
        foreach ($lista_vin_alunos_sei as $vin_aluno_sei) {
            array_push($lista_cpf_alunos, (object) ["cpf" => $vin_aluno_sei->cpf]);
        }
        $lista_cpf_alunos = array_unique($lista_cpf_alunos, SORT_REGULAR);

        $lista_usuarios_novos = [];
        foreach ($lista_cpf_alunos as $vin_aluno_sei) {
            // Consulta o usuário correspondente ao vinculo do aluno no SEI
            $pessoa_sei = array_filter($lista_pessoas_sei, function($pessoa_sei) use($vin_aluno_sei) {
                return $pessoa_sei->cpf == $vin_aluno_sei->cpf;
            });
            $pessoa_sei = array_values($pessoa_sei)[0];

            // Verifica se o aluno não tem usuário no moodle
            if(!$moodleController->consultaUsuarios('idnumber', $pessoa_sei->codigo, 1)){
                // Verifica se o email informado é válido
                if(!filter_var($pessoa_sei->email, FILTER_VALIDATE_EMAIL)){
                    LogController::ErroCriarUsuarioMoodleEmailInvalido($pessoa_sei);
                    break 1;
                }
                $usuario = [];
                $usuario['username'] = $pessoa_sei->cpf;
                $usuario['password'] = $pessoa_sei->cpf;
                $usuario['idnumber'] = $pessoa_sei->codigo;
                $usuario['firstname'] = explode(" ", trim($pessoa_sei->nome), 2)[0];
                $usuario['lastname'] = explode(" ", trim($pessoa_sei->nome), 2)[1];
                $usuario['email'] = strtolower(trim($pessoa_sei->email));
                $usuario['customfields'] = [];
                array_push($usuario['customfields'], ["type" => "CPF", "value" => $this->formataCPF($pessoa_sei->cpf)]);
                array_push($usuario['customfields'], ["type" => "token", "value" => $pessoa_sei->token]);
                $usuario['calendartype'] = 'gregorian';
                $usuario['timezone'] = 'America/Sao_Paulo';
                $usuario['lang'] = 'pt_br';

                array_push($lista_usuarios_novos, $usuario);
                unset($usuario);
            }
            unset($pessoa_sei);
        }
        unset($lista_pessoas_sei);
        unset($lista_cpf_alunos);
        unset($vin_aluno_sei);
        return $lista_usuarios_novos;
    }

    /**
     * Converte a lista de Curso|Turma|Modulo para um formato com apenas os dados necesários para a turma
     * @param array $lista_ctm_sei
     * @return array $lista_turmas_sei
     */
    function formataListaTurmasSei(array $lista_ctm_sei) : array {
        // Filtra da lista de turmas a serem convertidas em grupos, para cada vínculo de turma com módulo cria um código de grupo diferente
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
     * @param string $codigo_modulo
     * @return string $nome_breve
     */
    function geraNomeBreveCurso(string $nome_modulo, int $codigo_modulo) : string {
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

        return $nome_breve . "_" . $codigo_modulo;
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
