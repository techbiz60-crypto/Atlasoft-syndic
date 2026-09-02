<?php

namespace App;

enum Role: string
{
    case Admin = 'admin';
    case Tresorier = 'tresorier';
    case Conseil = 'conseil';
    case Coproprietaire = 'coproprietaire';
}
