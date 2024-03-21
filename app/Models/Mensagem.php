<?php

namespace App\Models;

class Mensagem {
    const MENSAGENS = [
        0 => "Não existe uma mensagem com o código informado.",
        // Faixa 200: mensagem de sucesso
        201 => "Categoria criada no moodle.",
        202 => "Curso criado no moodle.",
        203 => "Grupo criado no moodle.",
        204 => "Coorte criado no moodle.",
        205 => "Usuário criado no moodle.",
        206 => "Vínculo de usuário e curso criado no moodle.",
        207 => "Vínculo de usuário e grupo criado no moodle.",
        208 => "Vínculo de usuário e coorte criado no moodle.",
        // Faixa 400: mensagem de falhas
        401 => "Exceção reportada pela API do Moodle.",
        402 => "Não foi possível criar um usuário devido a e-mail inválido.",
        403 => "Não foi possível criar os vínculos de usuário e curso, grupo e coorte devido o usuário não existir no moodle.",
    ];

    static function getMensagem($codigo) : string {
        return (isset(self::MENSAGENS[$codigo]))?self::MENSAGENS[$codigo]:self::MENSAGENS[0];
    }
}