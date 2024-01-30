<?php

namespace App\Http\Controllers;

class PrincipalController extends Controller
{
    /** Período igual a 1 dia. Utilizado para cadastros ou alterações de registros. Ex.: cadastro de curso */
    const HORAS_CADASTRO = 24;
    /** Período igual a 6 meses. Utilizado para consultar relacionamento de outros registros. Ex.: usuário de um aluno */
    const HORAS_VINCULO = 4320;
    /** Período igual a 10 anos. Utilizado para consulta de registros não encontrados nos prazos anteriores. */
    const HORAS_PERIODO_COMPLETO = 87600;
    /** Representa o ano referente a quais turmas, cursos, disciplinas e alunos serão processados. */
    const ANO = 2024;
    /** Representa o semestre referente a quais turmas, cursos, disciplinas e alunos serão processados. */
    const SEMESTRE = 1;

    function principal(){
        $this->CadastrarCategoriasCursosGrupos();
        // ATUALIZAR USUÁRIOS
        $this->VincularAlunos();

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
    }

    function CadastrarCategoriasCursosGrupos() {
        $seiController = new SEIController();
        $moodleController = new MoodleController();
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
                if(!$moodleController->consultaCategorias('idnumber', $curso_sei->curso_codigo, 1)){
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
                if(!$moodleController->consultaCoortes('idnumber', 'curso - ' . $curso_sei->curso_codigo, 1)){
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
                if(!$moodleController->consultaCursos('idnumber', $modulo_sei->modulo_codigo, 1)){
                    $nome_breve = $this->geraNomeBreveCurso($modulo_sei->modulo_nome, $modulo_sei->modulo_codigo);
                    // Só cadastra os cursos na categoria raiz criada pelo sistema
                    $curso = [];
                    // Obs.: Datas não estão sendo cadastradas pois a data de início e fim da disciplinas podem variar conforme a turma 
                    $curso['categoryid'] = $categoria_raiz->id;
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

            $moodleController->criaCursos($lista_cursos);
            unset($lista_cursos);
            #endregion

            #region Cadastra grupos
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
                if(!$moodleController->consultaGrupos('idnumber', $turma_sei->grupo_codigo, 1)){
                    $cursos_moodle = $moodleController->consultaCursos('idnumber', $turma_sei->modulo_codigo, 1);

                    $grupo = [];
                    $grupo['courseid'] = $cursos_moodle->id;
                    $grupo['name'] = trim($turma_sei->turma_nome);
                    $grupo['idnumber'] = $turma_sei->grupo_codigo;
                    $grupo['description'] = 'Essa é a descrição do grupo referente ao vinculo da turma: "' . $turma_sei->turma_nome . '" com o curso: "' . $cursos_moodle->fullname . '".' ;
                    array_push($lista_grupos, $grupo);
                }
            }
            unset($turma_sei);
            unset($cursos_moodle);
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

        unset($lista_ctm_sei);
        unset($seiController);
        unset($moodleController);
    }

    function VincularAlunos() {
        $seiController = new SEIController();
        $moodleController = new MoodleController();

        #region Criar Aluno
        // Filtra da lista de alunos os com situação Ativo
        $lista_vin_alunos_sei = $seiController->vinculacoesAlunos_v2($this::HORAS_CADASTRO);
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
        //unset($lista_ctm_sei); Se essa lista não for necessária mais a frente, descomentar esse campo
        unset($vin_aluno_sei);

        // Identifica quais alunos ainda não tem usuários no Moodle
        $lista_pessoas_sei = $seiController->listarPessoas_v2($this::HORAS_VINCULO);
        $lista_usuarios_novos = [];
        foreach ($lista_vin_alunos_sei as $vin_aluno_sei) {
            $pessoa_sei = array_filter($lista_pessoas_sei, function($pessoa_sei) use($vin_aluno_sei) {
                return $pessoa_sei->cpf == $vin_aluno_sei->cpf;
            });
            $pessoa_sei = array_values($pessoa_sei)[0];

            $vin_aluno_sei->idnumber_usuario = $pessoa_sei->codigo;
            // Verifica se o aluno não tem usuário no moodle
            if(!$moodleController->consultaUsuarios('idnumber', $vin_aluno_sei->idnumber_usuario, 1)){
                $usuario = [];
                $usuario['username'] = $pessoa_sei->cpf;
                $usuario['password'] = $pessoa_sei->cpf;
                $usuario['idnumber'] = $pessoa_sei->codigo;
                $usuario['firstname'] = explode(" ", trim($pessoa_sei->nome), 2)[0];
                $usuario['lastname'] = explode(" ", trim($pessoa_sei->nome), 2)[1];
                $usuario['email'] = strtolower(trim($pessoa_sei->email));
                $usuario['customfields'] = [];
                array_push($usuario['customfields'], (object)["type" => "CPF", "value" => $this->formataCPF($pessoa_sei->cpf)]);
                array_push($usuario['customfields'], (object)["type" => "token", "value" => $pessoa_sei->token]);
                $usuario['calendartype'] = 'gregorian';
                $usuario['timezone'] = 'America/Sao_Paulo';
                $usuario['lang'] = 'pt_br';
                
                array_push($lista_usuarios_novos, $usuario);
                unset($usuario);
            }
            unset($pessoa_sei);
        }
        unset($lista_pessoas_sei);
        unset($vin_aluno_sei);

        // Cria os usuários que ainda não tem cadastro no moodle
        $lista_usuarios_novos = array_unique($lista_usuarios_novos, SORT_REGULAR);
        //VERIFICAR RETORNO PARA VER SE TEVE SUCESSO OU NÃO
        $retorno = $moodleController->criaUsuarios($lista_usuarios_novos);
        unset($lista_usuarios_novos);

        $lista_vin_alunos_moodle = [];
        // Cadastra os novos vinculos no moodle
        foreach ($lista_vin_alunos_sei as $vin_aluno_sei) {
            $usuario_moodle = $moodleController->consultaUsuarios('idnumber', $vin_aluno_sei->idnumber_usuario, 1);
            // Verificar se o usuário não existir
            $curso_moodle = $moodleController->consultaCursos('idnumber', $vin_aluno_sei->modulo_codigo, 1);
            // Verificar se o curso não existir
            $ctm_sei = array_values(array_filter($lista_ctm_sei, function($ctm_sei) use($vin_aluno_sei) {
                return $ctm_sei->curso->curso_codigo == $vin_aluno_sei->curso_codigo && $ctm_sei->turma->turma_codigo == $vin_aluno_sei->turma_codigo && $ctm_sei->modulo->modulo_codigo == $vin_aluno_sei->modulo_codigo;
            }))[0];

            // Verificar se o curso não existir
            $vin_aluno_moodle = [];
            $vin_aluno_moodle['roleid'] = '5';
            $vin_aluno_moodle['userid'] = $usuario_moodle->id;
            $vin_aluno_moodle['courseid'] = $curso_moodle->id;
            $vin_aluno_moodle['timestart'] = $ctm_sei->modulo->modulo_inicio;
            $vin_aluno_moodle['timeend'] = $ctm_sei->modulo->modulo_fim;

            array_push($lista_vin_alunos_moodle, $vin_aluno_moodle);
            unset($vin_aluno_moodle);
            unset($curso_moodle);
            unset($usuario_moodle);
            unset($ctm_sei);
        }
        unset($vin_aluno_sei);

        $retorno = $moodleController->criaVinculosUsuarioCurso($lista_vin_alunos_moodle);
        unset($lista_vin_alunos_moodle);

        // Identifica a qual grupo ele deve ser vinculado no moodle
        // SE O GRUPO NÃO EXISTE?
        #endregion
    }

    /**
     * Através do nome e código do módulo o nome breve que será utilizado no cadastro do curso no moodle
     * @param string $nome_modulo
     * @param string $codigo_modulo
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
     * // VERIFICAR RETORNOS DE EXCEÇÕES
     * @param string $cpf
     */
    function formataCPF(string $cpf) : string {
        // Remove caracteres não numéricos
        $cpf = preg_replace("/\D/", '', $cpf);
        // Divide a string em conjuntos de 3 e 2 números e intercala . e - entre esses conjuntos
        return preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "\$1.\$2.\$3-\$4", $cpf);
    }
}
