<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

/**
 * Classe responsável por consultar e manipular os dados presentes no SEI através de seu WebService
 * Obs.: Devido ao tempo de resposta menor das funções V2, todas as funções originais equivalentes foram removidas
 */
class SEIController extends Controller {
    private const URL_API_SEI = "https://fsensu.com/webservice/moodle";
    private const CABECALHO_API_SEI = [
        'Content-Type' =>  'application/json',
        'Authorization' => '9510905a5d214feef0d5368d88cdc4264c2dbae4e505ac00a4adf50393c50fd6'
      ];

    #region métodos da API
    function listarPessoas_v2 ($horas = 0, $unidadeEnsino = 0, $curso = 0, $nivelEducacional = "TODOS", $ano = 0, $semestre = 0, $tipoPessoa = "TODAS", $validarEmail = 0, $validarCpf = 0, $limit = 0, $pagina = 0) {
        $url = self::URL_API_SEI . '/V2/pessoas/' . strval($unidadeEnsino) . '/' . strval($curso) . '/' . strval($nivelEducacional) . '/' . strval($ano) . '/' . strval($semestre) . '/' . strval($tipoPessoa) . '/' . strval($validarEmail) . '/' . strval($validarCpf) . '/' . strval($horas) . '/' . strval($limit) . '/' . strval($pagina);
        $resposta = Http::withHeaders(self::CABECALHO_API_SEI)->get($url);
        $pessoas = json_decode($resposta->body());

        return ($pessoas != NULL)?$pessoas:[];
    }

    function listarCursos_v2 ($horas = 0, $unidadeEnsino = 0, $curso = 0, $nivelEducacional = "TODOS", $ano = 0, $semestre = 0, $limit = 0, $pagina = 0) {
        $url = self::URL_API_SEI . '/V2/cursos/' . strval($unidadeEnsino) . '/' . strval($curso) . '/' . strval($nivelEducacional) . '/' . strval($ano) . '/' . strval($semestre) . '/' . strval($horas) . '/' . strval($limit) . '/' . strval($pagina);
        $resposta = Http::withHeaders(self::CABECALHO_API_SEI)->get($url);
        $cursos = json_decode($resposta->body());

        return ($cursos != NULL)?$cursos:[];
    }
    
    function listarVinculoAlunos_v2 ($horas = 0, $unidadeEnsino = 0, $curso = 0, $nivelEducacional = "TODOS", $ano = 0, $semestre = 0, $limit = 0, $pagina = 0) {
        $url = self::URL_API_SEI . '/V2/vinculacoes/alunos/' . strval($unidadeEnsino) . '/' . strval($curso) . '/' . strval($nivelEducacional) . '/' . strval($ano) . '/' . strval($semestre) . '/' . strval($horas) . '/' . strval($limit) . '/' . strval($pagina);
        $resposta = Http::withHeaders(self::CABECALHO_API_SEI)->get($url);
        $alunos = json_decode($resposta->body());

        return ($alunos != NULL)?$alunos:[];
    }

    function listarVinculoProfessores_v2 ($horas = 0, $unidadeEnsino = 0, $curso = 0, $nivelEducacional = 0, $ano = 0, $semestre = 0, $limit = 0, $pagina = 0) {
        $url = self::URL_API_SEI . '/V2/vinculacoes/professores/' . strval($unidadeEnsino) . '/' . strval($curso) . '/' . strval($nivelEducacional) . '/' . strval($ano) . '/' . strval($semestre) . '/' . strval($horas) . '/' . strval($limit) . '/' . strval($pagina);
        $resposta = Http::withHeaders(self::CABECALHO_API_SEI)->get($url);
        $professores = json_decode($resposta->body());

        return ($professores != NULL)?$professores:[];
    }

    function listarVinculoCoordenadores_v2 ($horas = 0, $unidadeEnsino = 0, $curso = 0, $nivelEducacional = 0, $ano = 0, $semestre = 0, $limit = 0, $pagina = 0) {
        $url = self::URL_API_SEI . '/V2/vinculacoes/coordenadores/' . strval($unidadeEnsino) . '/' . strval($curso) . '/' . strval($nivelEducacional) . '/' . strval($ano) . '/' . strval($semestre) . '/' . strval($horas) . '/' . strval($limit) . '/' . strval($pagina);
        $resposta = Http::withHeaders(self::CABECALHO_API_SEI)->get($url);
        $coordenadores = json_decode($resposta->body());

        return ($coordenadores != NULL)?$coordenadores:[];
    }

    function materiaisDisponibilizados ($horas = 0) {
        $url = self::URL_API_SEI . '/materiais/' . strval($horas);
        $resposta = Http::withHeaders(self::CABECALHO_API_SEI)->get($url);
        $materiais = json_decode($resposta->body());

        return ($materiais != NULL)?$materiais:[];
    }

    function downloadMaterial ($codigo = 0) {
        $url = self::URL_API_SEI . '/material/' . strval($codigo);
        $resposta = Http::withHeaders(self::CABECALHO_API_SEI)->get($url);
        $material = json_decode($resposta->body());

        return ($material != NULL)?$material:[];
    }

    function materiaisExcluidos ($horas = 0) {
        $url = self::URL_API_SEI . '/materiaisRemovidos/' . strval($horas);
        $resposta = Http::withHeaders(self::CABECALHO_API_SEI)->get($url);
        $materiais = json_decode($resposta->body());

        return ($materiais != NULL)?$materiais:[];
    }

    // Não testada
    function vincularNotasDisciplinaAluno ($cpf = "", $curso_codigo = "", $turma_codigo = "", $modulo_codigo = "", $notas = [], $frequencia = "", $calcular_media = true) {
        $notas_enviar = [];
        for ($i=0; $i < count($notas); $i++) {
            $objeto_nota = (object) [];
            $objeto_nota->cpf = $cpf; 
            $objeto_nota->curso_codigo = $curso_codigo;
            $objeto_nota->turma_codigo = $turma_codigo;
            $objeto_nota->modulo_codigo = $modulo_codigo;
            $objeto_nota->nota = $notas[$i]->valor;
            $objeto_nota->frequencia = $frequencia;
            $objeto_nota->variavel_nota = $notas[$i]->chave;
            $objeto_nota->calcular_media = $calcular_media;
            array_push($notas_enviar, $objeto_nota);
        }
        $url = self::URL_API_SEI . '/vinculacoes/notas';
        $resposta = Http::withHeaders(self::CABECALHO_API_SEI)->post($url, $notas_enviar);
        $resposta = json_decode($resposta->body());

        return $resposta;
    }

    // Não testada
    function registrarMensagem ($cpf_remetente = "", $mensagem = "", $assunto = "", $cpf_destinatarios = []) {
        $mensagem = (object) [];
        $mensagem->cpf_remetente = $cpf_remetente; 
        $mensagem->mensagem = $mensagem;
        $mensagem->assunto = $assunto;
        $mensagem->cpf_destinatarios = '';
        for ($i=0; $i < count($cpf_destinatarios) ; $i++) { 
            $mensagem->cpf_destinatarios .= $cpf_destinatarios;
            if($i != count($cpf_destinatarios)-1){
                $mensagem->cpf_destinatarios .= ',';
            }
        }
        $url = self::URL_API_SEI . '/mensagem';
        $resposta = Http::withHeaders(self::CABECALHO_API_SEI)->post($url, [$mensagem]);
        $resposta = json_decode($resposta->body());

        return $resposta;
    }
    #endregion
}