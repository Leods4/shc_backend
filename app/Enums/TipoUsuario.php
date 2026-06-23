<?php

namespace App\Enums;

enum TipoUsuario: string
{
    case ALUNO = 'ALUNO';
    case COORDENADOR = 'COORDENADOR';
    case SECRETARIA = 'SECRETARIA';
    case ADMINISTRADOR = 'ADMINISTRADOR';
}
