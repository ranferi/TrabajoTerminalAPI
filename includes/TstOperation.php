<?php

class TstOperation
{
    private $gs;
    private $endpoint;

    function __construct()
    {
        require_once dirname(__FILE__) . '/Constants.php';
        require '../vendor/autoload.php';

        EasyRdf_Namespace::set('su', 'http://www.semanticweb.org/ranferi/ontologies/2018/9/ssrsi_onto#');
        EasyRdf_Namespace::set('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
        EasyRdf_Namespace::set('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
        EasyRdf_Namespace::set('owl', 'http://www.w3.org/2002/07/owl#');

        $config = array("timeout"=>500, "maxredirects" => 6);
        EasyRdf_Http::setDefaultHttpClient(
             $this->client = new EasyRdf_Http_Client(null, $config)
         );

        $this->gs = new  EasyRdf_GraphStore('http://localhost:3030/repositories/ssrsi/rdf-graphs/service');

        $this->endpoint = new EasyRdf_Sparql_Client("http://localhost:3030/repositories/ssrsi",
            "http://localhost:3030/repositories/ssrsi/statements");
    }

    /***
     * Crea un usuario en la TripleStore
     *
     * @param $usuario
     * @param $email
     * @param $password
     * @param $nombre
     * @param $apellido_paterno
     * @param $apellido_materno
     * @return int una de las constantes definidas
     */
    function createUser($usuario, $email, $password, $nombre, $apellido_paterno, $apellido_materno)
    {
        if (!$this->checkUserExist($usuario) && !$this->checkEmailExist($email)) {
            $pass_md5 = md5($password);
            $id = mt_rand(700000, 800000);

            while ($this->checkIDExist($id, "user")) {
                $id = mt_rand(700000, 800000);
            }
            $resource = "su:user_" . $id;

            $graph = new EasyRdf_Graph();
            $graph->add($resource, 'su:usuario', $usuario);
            $graph->add($resource, 'su:email', $email);
            $graph->add($resource, 'su:password', $pass_md5);
            $graph->add($resource, 'su:nombre', $nombre);
            $graph->add($resource, 'su:idUsuario', $id);
            $graph->add($resource, 'su:apellidoPaterno', $apellido_paterno);
            $graph->add($resource, 'su:apellidoMaterno', $apellido_materno);
            $response = $this->gs->insertIntoDefault($graph);

            if ($response->isSuccessful())
                return USER_CREATED;
            return USER_CREATION_FAILED; 
        }
        return USER_EXIST;
    }

    /***
     * Método para revisar si el correo ya existe en ontología
     *
     * @param $email
     * @return bool
     */
    function checkEmailExist($email)
    {
        $result = $this->endpoint->query(
            'SELECT * WHERE {' .
            ' ?usuario su:email "' . $email . '" ' .
            '}'
        );
        return $result->numRows() > 0;
    }

    /***
     * Método para revisar si el correo ya existe en ontología
     *
     * @param $user
     * @return bool
     */
    function checkUserExist($user)
    {
        $result = $this->endpoint->query(
            'SELECT * WHERE {' .
            ' ?usuario su:usuario "' . $user . '" ' .
            '}'
        );

        return $result->numRows() > 0;
    }


    /**
     * Método para 'logear' usuario
     *
     * @param $email
     * @param $pass
     * @return bool
     */
    function userLogin($email, $pass)
    {
        $password = md5($pass);
        $result = $this->endpoint->query("
        SELECT ?id
        WHERE {
            ?sujeto su:email \"$email\" .
            ?sujeto su:password \"$password\".
            ?sujeto su:idUsuario ?id .
        }"
        );

        return $result->numRows()  > 0;
    }

    /***
     * Método para actualizar al usuario
     *
     * @param $id
     * @param $usuario
     * @param $email
     * @param $pass
     * @param $nombre
     * @param $apellido_paterno
     * @param $apellido_materno
     * @return bool
     */
    function updateProfile($id, $usuario, $email, $pass, $nombre, $apellido_paterno, $apellido_materno)
    {
        $pass_md5 = md5($pass);

        $response = $this->endpoint->update("
            DELETE {?s ?p ?o}
            INSERT {
              ?s su:nombre \"$nombre\" ;
                su:email \"$email\" ;
                su:usuario \"$usuario\" ;
                su:apellidoPaterno \"$apellido_paterno\" ;
                su:apellidoMaterno \"$apellido_materno\" ;
                su:password \"$pass_md5\" ;
                su:idUsuario " . $id . " ." .
            "}
            WHERE  {
               ?s su:idUsuario " . $id . " ." .
            "?s ?p ?o . 
                  FILTER(isUri(?p) && STRSTARTS(STR(?p), STR(su:)))
            }"
        );

        return $response->isSuccessful();
    }

    /**
     * Método para obtener un usuario por su email
     * @param $user
     * @return array
     */
    function getUserByUsername($user)
    {
        $result = $this->endpoint->query("
        SELECT ?sujeto ?id ?nombre ?usuario ?apellidoPaterno ?apellidoMaterno
        WHERE {
            ?sujeto su:usuario \"$user\" .
            ?sujeto su:idUsuario ?id .
            ?sujeto su:nombre ?nombre .
            ?sujeto su:apellidoPaterno ?apellidoPaterno .
            ?sujeto su:apellidoMaterno ?apellidoMaterno .
            ?sujeto su:email ?email.
        }"
        );
        $user = array();

        if ($result->numRows() == 1) {
            $user['id'] = $result->current()->id->getValue();
            $user['name'] = $result->current()->nombre->getValue();
            $user['lastName'] = $result->current()->apellidoPaterno->getValue();
            $user['mothersMaidenName'] = $result->current()->apellidoMaterno->getValue();
            $user['user'] = $user;
            $user['email'] = $result->current()->email->getValue();
        }

        return $user;
    }

    /**
     * Método para obtener un usuario por su email
     *
     * @param $email
     * @return array
     */
    function getUserByEmail($email)
    {
        $result = $this->endpoint->query("
        SELECT ?sujeto ?id ?nombre ?usuario ?apellidoPaterno ?apellidoMaterno
        WHERE {
            ?sujeto su:email \"$email\" .
            ?sujeto su:idUsuario ?id .
            ?sujeto su:nombre ?nombre .
            ?sujeto su:apellidoPaterno ?apellidoPaterno .
            ?sujeto su:apellidoMaterno ?apellidoMaterno .
            ?sujeto su:usuario ?usuario .
        }");

        $user = array();
        if ($result->numRows() == 1) {
            $user['id'] = $result->current()->id->getValue();
            $user['name'] = $result->current()->nombre->getValue();
            $user['lastName'] = $result->current()->apellidoPaterno->getValue();
            $user['mothersMaidenName'] = $result->current()->apellidoMaterno->getValue();
            $user['email'] = $email;
            $user['user'] = $result->current()->usuario->getValue();
        }
        return $user;
    }

    /**
     * Método para enlistar todos los usuarios en la ontología
     *
     * @return array
     */
    function getAllUsers()
    {
        $result = $this->endpoint->query("
        SELECT ?sujeto ?id ?nombre ?usuario  ?email
        WHERE {
            ?sujeto su:email ?email.
            ?sujeto su:idUsuario ?id .
            ?sujeto su:nombre ?nombre .
            ?sujeto su:usuario ?usuario .
        }"
        );
        $users = array();

        foreach ($result as $user) {
            $temp = array();
            $temp['id'] = $user->id->getValue();
            $temp['name'] = $user->nombre->getValue();
            $temp['usuario'] = $user->usuario->getValue();
            $temp['email'] = $user->email->getValue();
            array_push($users, $temp);
        }
        return $users;
    }

    function getAllVisitedPlacesByUser($id)
    {
        $user = $this->endpoint->query("
        SELECT ?sujeto ?nombre ?usuario  ?email
        WHERE {
            ?sujeto su:idUsuario " . $id . " .
            ?sujeto su:nombre ?nombre .
            ?sujeto su:usuario ?usuario .
            ?sujeto su:email ?email .
        }");
        $users = array();
        $temp = array();
        $temp['id'] = intval($id);
        $temp['nombre'] = $user->current()->nombre->getValue();
        $temp['usuario'] = $user->current()->usuario->getValue();
        $temp['email'] = $user->current()->email->getValue();
        $temp['visito'] = array();

        $visited = $this->endpoint->query("
        SELECT ?sujeto ?a ?sitio ?precio ?comentario ?c ?gusto
        WHERE {
            ?sujeto su:idUsuario " . $temp['id'] . " .
            ?sujeto su:visito ?a . 
            ?a su:sitioVisitado ?sitio .
            ?a su:daCalificacionPrecio/su:calificacionDeUsuarioPrecio ?precio .
            ?a su:dejaComentario ?c . 
            ?c su:conComentario ?comentario .
            ?a su:leGusto ?gusto .
        }");

        $temp_1 = array();
        foreach ($visited as $place) {
            $temp_comentario = array();
            $temp_usuario = array();

            $id_visitado = $this->getIdFromURI($place->a->getUri(), "r_user_place_");
            $id_comentario = $this->getIdFromURI($place->c->getUri(), "r_user_comment_");

            $temp_usuario['id'] = $temp['id'];
            $temp_usuario['usuario'] = $temp['usuario'];
            $temp_usuario['email'] = $temp['email'];

            $temp_comentario['comentario'] = $place->comentario->getValue();
            $temp_comentario['id'] = $id_comentario;
            $temp_comentario['user'] = $temp_usuario;

            $temp_1['id'] = $id_visitado;
            $temp_1['sitio_src'] = $place->sitio->shorten();
            $temp_1['precio'] = $place->precio->shorten();
            $temp_1['gusto'] = $place->gusto->getValue();
            $temp_1['comentario'] = $temp_comentario;
            $temp_1['sitio'] = array();

            // Propiedades
            $temp_2 = $this->getBasePropsPlace($temp_1['sitio_src']);

            // Nombres
            $temp_2['nombres'] = $this->getNamesOfPlace($temp_1['sitio_src']);

            // Calificaciones
            $ratings = $this->getRatingsOfPlace($temp_1['sitio_src']);
            $temp_2['calificaciones'] = $ratings;
            $temp_2['total'] = $this->getRatingsTotal($ratings);

            // Categorias
            $temp_2['categorias'] = $this->getCategoriesOfPlace($temp_1['sitio_src']);

            // Imagenes
            $temp_2['imagenes'] = $this->getImagesOfPlace($temp_1['sitio_src']);

            // Comentarios
            $temp_comments1 = $this->getCommentsOfPlace($temp_1['sitio_src']);
            $temp_comments2 = $this->getCommentOfPlaceFromUser($temp_1['sitio_src'], $temp['id']);
            $comments = array_merge($temp_comments1, $temp_comments2);

            $temp_2['comentarios'] = $comments;

            array_push($temp_1['sitio'], $temp_2);
            array_push($temp['visito'], $temp_1);

        }
        array_push($users, $temp);

        return $users;
    }

    function searchPlaces($id, $type, $price, $distance, $music)
    {
        $user = $this->endpoint->query("
        SELECT ?sujeto ?nombre ?usuario  ?email
        WHERE {
            ?sujeto su:idUsuario " . $id . " .
            ?sujeto su:nombre ?nombre .
            ?sujeto su:usuario ?usuario .
            ?sujeto su:email ?email .
        }");
        $users = array();
        $temp = array();
        $temp['id'] = intval($id);
        $temp['nombre'] = $user->current()->nombre->getValue();
        $temp['usuario'] = $user->current()->usuario->getValue();
        $temp['email'] = $user->current()->email->getValue();
        $temp['visito'] = array();

        $visited = $this->endpoint->query("
        SELECT ?sujeto ?a ?sitio ?precio ?comentario ?c ?gusto
        WHERE {
            ?sujeto su:idUsuario " . $temp['id'] . " .
            ?sujeto su:visito ?a . 
            ?a su:sitioVisitado ?sitio .
            ?a su:daCalificacionPrecio/su:calificacionDeUsuarioPrecio ?precio .
            ?a su:dejaComentario ?c . 
            ?c su:conComentario ?comentario .
            ?a su:leGusto ?gusto .
        }");

        $temp_1 = array();
        foreach ($visited as $place) {
            $temp_comentario = array();
            $temp_usuario = array();

            $id_visitado = $this->getIdFromURI($place->a->getUri(), "r_user_place_");
            $id_comentario = $this->getIdFromURI($place->c->getUri(), "r_user_comment_");

            $temp_usuario['id'] = $temp['id'];
            $temp_usuario['usuario'] = $temp['usuario'];
            $temp_usuario['email'] = $temp['email'];

            $temp_comentario['comentario'] = $place->comentario->getValue();
            $temp_comentario['id'] = $id_comentario;
            $temp_comentario['user'] = $temp_usuario;

            $temp_1['id'] = $id_visitado;
            $temp_1['sitio_src'] = $place->sitio->shorten();
            $temp_1['precio'] = $place->precio->shorten();
            $temp_1['gusto'] = $place->gusto->getValue();
            $temp_1['comentario'] = $temp_comentario;
            $temp_1['sitio'] = array();

            // Propiedades
            $temp_2 = $this->getBasePropsPlace($temp_1['sitio_src']);

            // Nombres
            $temp_2['nombres'] = $this->getNamesOfPlace($temp_1['sitio_src']);

            // Calificaciones
            $ratings = $this->getRatingsOfPlace($temp_1['sitio_src']);
            $temp_2['calificaciones'] = $ratings;
            $temp_2['total'] = $this->getRatingsTotal($ratings);

            // Categorias
            $temp_2['categorias'] = $this->getCategoriesOfPlace($temp_1['sitio_src']);

            // Imagenes
            $temp_2['imagenes'] = $this->getImagesOfPlace($temp_1['sitio_src']);

            // Comentarios
            $temp_comments1 = $this->getCommentsOfPlace($temp_1['sitio_src']);
            $temp_comments2 = $this->getCommentOfPlaceFromUser($temp_1['sitio_src'], $temp['id']);
            $comments = array_merge($temp_comments1, $temp_comments2);

            $temp_2['comentarios'] = $comments;

            array_push($temp_1['sitio'], $temp_2);
            array_push($temp['visito'], $temp_1);

        }
        array_push($users, $temp);

        return $users;
    }

    function getAllPoints()
    {
        $result = $this->endpoint->query("
        SELECT ?sujeto ?id ?medi ?latitud ?longitud ?dir ?musica ?idUsuarioSitio ?calif ?comm ?idComm ?gusto 
        WHERE {
            ?sujeto su:idSitio ?id .
            ?sujeto a [
                a owl:Restriction;
                owl:onProperty su:tieneUnValorMEDI;
                owl:someValuesFrom ?medi
            ] .
            ?sujeto su:tienePropiedad/su:direccionSitio ?dir .
            ?sujeto su:tienePropiedad/su:musica ?musica .
            OPTIONAL { ?sujeto su:tienePropiedad/su:latitud ?latitud . }
            OPTIONAL { ?sujeto su:tienePropiedad/su:longitud ?longitud . }
            OPTIONAL {
                ?user su:idUsuario 768177 .
                ?user su:visito ?v .
                ?v su:sitioVisitado ?sujeto .
                ?v su:idUsuarioSitio ?idUsuarioSitio .
                ?v su:daCalificacionPrecio/su:calificacionDeUsuarioPrecio ?calif .
                ?v su:dejaComentario ?c . 
                ?c su:conComentario ?comm .
                ?c su:idUsuarioComen ?idComm .
                ?v su:leGusto ?gusto .
            }
        }
        LIMIT 5"
        );

        $points = array();
        foreach ($result as $place) {
            $sujeto = $place->sujeto->shorten();
            $temp_id = $place->id->getValue();

            $temp = array();
            $temp['id'] = $temp_id;
            $temp['medi'] = $place->medi->localName();
            if (isset($place->latitud))
                $temp['latitud'] = $place->latitud->getValue();
            if (isset($place->longitud))
                $temp['longitud'] = $place->longitud->getValue();
            $temp['direccion'] = $place->dir->getValue();
            $temp['musica'] = $place->musica->getValue();

            // Visito el sitio
            $visito = array();

            if (isset($place->idUsuarioSitio))
                $visito['id'] = $place->idUsuarioSitio->getValue();
            if (isset($place->gusto))
                $visito['gusto'] = $place->gusto->getValue();
            if (isset($place->calif))
                $visito['precio'] = $place->calif->shorten();
            $visitantes = array();
            $visitantes['id'] = 768177;
            $visitantes['usuario'] = "incubo";
            $visitantes['email'] = "usuario@gmail.com";
            // $visito['visitantes'] = $visitantes;
            $temp['visitaron'] = $visito;
            $comentario = array();
            if (isset($place->idComm))
                $comentario['id'] = intval($place->idComm->getValue());
            if (isset($place->comm))
                $comentario['comentario'] = $place->comm->getValue();
            $visito['comentario'] = $comentario;
            /*
             *
            PREFIX su: <http://www.semanticweb.org/ranferi/ontologies/2018/9/ssrsi_onto#>
PREFIX owl: <http://www.w3.org/2002/07/owl#>
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
INSERT DATA {
    su:user_768177 su:visito _:r_user_place_5 .
    _:r_user_place_5 a su:RelacionUsuarioSitio .
    _:r_user_place_5 su:idUsuarioSitio 200001 .
    _:r_user_place_5 su:sitioVisitado su:place_14005 .
    _:r_user_place_5 su:daCalificacionPrecio _:r_user_rating_5 .
    _:r_user_place_5 su:dejaComentario _:r_user_comment_5 .
    _:r_user_place_5 su:leGusto "true"^^xsd:boolean .

    _:r_user_rating_5 a su:RelacionUsuarioCalificacionPrecio .
    _:r_user_rating_5 su:idUsuarioCalif 300001 .
    _:r_user_rating_5 su:calificacionDeUsuarioPrecio su:Barato .

    _:r_user_comment_5 a su:RelacionUsuarioComentario .
    _:r_user_comment_5 su:idUsuarioComen 400001 .
    _:r_user_comment_5 su:conComentario "Esta bienasdsadas" .
}
            */


            // Nombres
            $temp['nombres'] = $this->getNamesOfPlace($sujeto);

            // Calificaciones
            $ratings = $this->getRatingsOfPlace($sujeto);
            $temp['calificaciones'] = $ratings;
            $temp['total'] = $this->getRatingsTotal($ratings);

            // Categorias
            $temp['categorias'] = $this->getCategoriesOfPlace($sujeto);

            // Imagenes
            $temp['imagenes'] = $this->getImagesOfPlace($sujeto);

            // Comentarios
            $temp_comments1 = $this->getCommentsOfPlace($sujeto );
            $temp_comments2 = $this->getCommentOfPlaceFromUser($sujeto);
            $comments = array_merge($temp_comments1, $temp_comments2);
            $temp['comentarios'] = $comments;

            array_push($points, $temp);
        }
        return $points;
    }

    function insertUserRatingSite($id, $idPlace, $liked, $price, $comment) 
    {
        $recursos = $this->checkIfSiteVisited($id, $idPlace);
        $id_r1 = $this->createID(200000, 300000, "user_place");
        $id_r2 = $this->createID(300000, 400000, "rating");
        $id_r3 = $this->createID(400000, 500000, "comment");
        if (!empty($recursos)) {
            // existe el vinculo de un usuario con un sitio visitado
            $query = "SELECT DISTINCT ?o 
                      WHERE {" . $this->stringWhereQuery($id, $idPlace) . 
                      " FILTER(isUri(?o1) && STRSTARTS(STR(?o1), STR(su:))) }";
            $res_rel = $this->endpoint->query($query);
            
            $r = array();
            foreach ($res_rel as $prop) {
                $r = array_merge($r, $this->typeBlankNode($prop->o->getUri()));
            }
            $recursos = array_merge($recursos, $r);

            $delete_query = "DELETE { ?u su:visito ?r . ?r ?p ?o . ?o ?p1 ?o1 . }";
            $delete_query .= "WHERE {". $this->stringWhereQuery($id, $idPlace) . " }";
            $response = $this->endpoint->update($delete_query);
            // echo '<pre>' . var_export($response->isSuccessful(), true) . '</pre>';
            if (!$response->isSuccessful()) {
                return false;
            }
        } else {
            // no existe un sitio visitado
            $recursos["usuario"]  = "su:user_" . strval($id);
            $recursos["sitio"] = "su:place_" . strval($idPlace);
            $recursos["usuario_sitio"] = "_:r_user_place_" . strval($id_r1);
            $recursos["usuario_calif"] = "_:r_user_rating_" . strval($id_r2);
            $recursos["usuario_comen"] = "_:r_user_comment_" . strval($id_r3);
            // echo '<pre>' . var_export($recursos, true) . '</pre>';
        }
        
        $insert_query = "INSERT DATA {\n" .
            $recursos["usuario"] . " su:visito " . $recursos["usuario_sitio"] . " .\n" .
            $recursos["usuario_sitio"] . " a su:RelacionUsuarioSitio .\n" .
            $recursos["usuario_sitio"] . " su:idUsuarioSitio " . $id_r1 . " .\n" .
            $recursos["usuario_sitio"] . " su:sitioVisitado " . $recursos["sitio"] . " .\n" .
            $recursos["usuario_sitio"] . " su:daCalificacionPrecio " . $recursos["usuario_calif"] . " .\n" .
            $recursos["usuario_sitio"] . " su:dejaComentario " . $recursos["usuario_comen"] . " .\n" .
            $recursos["usuario_sitio"] . " su:leGusto \"" . $liked . "\"^^xsd:boolean .\n" .

            $recursos["usuario_calif"] . " a su:RelacionUsuarioCalificacionPrecio .\n" .
            $recursos["usuario_calif"] . " su:idUsuarioCalif " . $id_r2 . " .\n" .
            $recursos["usuario_calif"] . " su:calificacionDeUsuarioPrecio su:" . $price . " .\n" .

            $recursos["usuario_comen"] . " a su:RelacionUsuarioComentario .\n" .
            $recursos["usuario_comen"] . " su:idUsuarioComen " . $id_r3 . " .\n" .
            $recursos["usuario_comen"] . " su:conComentario \"" . $comment . "\" .\n" . "}";
        // echo '<pre>' . var_export($insert_query, true) . '</pre>';

        return $this->endpoint->update($insert_query);
    }

    function checkIfSiteVisited($id, $idPlace) {
        $query = "SELECT ?s ?p 
        WHERE { 
            ?s su:idUsuario " . strval($id) . " . 
            ?s su:visito ?p .
            ?p su:sitioVisitado su:place_" . strval($idPlace) . " . 
        }";
        $result = $this->endpoint->query($query);
        $relacion = array();
        if ($result->numRows() > 0) {
            $relacion["usuario"] = $result->current()->s->shorten();
            $relacion["sitio"] = "su:place_" . strval($idPlace);
        }
        return $relacion;
    }

    /**
     * Método para crear un ID en la ontología
     *
     * @param $lower
     * @param $upper
     * @param $type
     * @return int
     */
    function createID($lower, $upper, $type)
    {
        $id = mt_rand($lower, $upper);
        while ($this->checkIDExist($id, $type)) {
            $id = mt_rand($lower, $upper);
        }
        return $id;
    }

    /**
     * Método para revisar si existe ya un ID en la ontología
     *
     * @param $id
     * @return bool
     */
    function checkIDExist($id, $type)
    {
        $query = "";
        switch ($type) {
            case "user":
                $query = "SELECT * WHERE { ?s su:idUsuario " . strval($id) . " . }";
                break;
            case "user_place":
                $query = "SELECT * WHERE { ?s su:idUsuarioSitio " . strval($id) . " . }";
                break;
            case "rating":
                $query = "SELECT * WHERE { ?s su:idUsuarioCalif " . strval($id) . " . }";
                break;
            case "comment":
                $query = "SELECT * WHERE { ?s su:idUsuarioComen " . strval($id) . " . }";
                break;
            /*case "user_place":
                $query = "SELECT * WHERE { ?s su:visito _:r_user_place_" . strval($id) . " . }";
                break;
            case "rating":
                $query = "SELECT * WHERE { ?s su:daCalificacionPrecio _:r_user_rating_" . strval($id) . " . }";
                break;
            case "comment":
                $query = "SELECT * WHERE { ?s su:dejaComentario _:r_user_comment_" . strval($id) . " . }";
                break;*/
        }
        $result = $this->endpoint->query($query);
        return $result->numRows() > 0;
    }

    function getIdFromURI($URI, $substr) {
        $sub_uri = substr($URI, strrpos($URI, $substr));
        return intval(substr($sub_uri, strripos($sub_uri, "_") + 1));
    }

    function typeBlankNode($temp) 
    {
        $blank = substr($temp, strrpos($temp, "r_user_"));
        $type  = $this->reverse_strrchr($blank, "_", 1);
        $r = array();
        switch ($type) {
                    case "r_user_rating_":
                        $r["usuario_calif"] = "_:" . $blank;
                        return $r;
                    case "r_user_comment_":
                        $r["usuario_comen"] = "_:" . $blank;
                        return $r;
                    case "r_user_place_":
                        $r["usuario_sitio"] = "_:" . $blank;
                        return $r;
                    default:
                        $r["error"] = true;
                        return $r;
                }
    }


    function getBasePropsPlace($place_src)
    {
        /*$names = array();
        $result = $this->endpoint->query("
                SELECT ?nombreSitio ?base
                WHERE {
                    " . $place_src . " su:tienePropiedad ?prop .
                    ?prop su:nombreSitio ?nombreSitio .
                    ?prop su:provieneDeBD ?base .
                }"
        );
        foreach ($result as $name) {
            $temp_2 = array();
            $temp_2['nombre_sitio'] = $name->nombreSitio->getValue();
            $temp_2['proviene'] = $name->base->localName();
            array_push($names, $temp_2);
        }*/


        $string = "
                SELECT ?id ?medi ?latitud ?longitud ?dir ?musica
                WHERE {
                    " . $place_src . " su:idSitio ?id .
                    " . $place_src . " a [
                        a owl:Restriction;
                        owl:onProperty su:tieneUnValorMEDI;
                        owl:someValuesFrom ?medi
                    ] .
                    " . $place_src . " su:tienePropiedad/su:direccionSitio ?dir .
                    " . $place_src . " su:tienePropiedad/su:musica ?musica .
                    OPTIONAL { " . $place_src . " su:tienePropiedad/su:latitud ?latitud . }
                    OPTIONAL { " . $place_src . " su:tienePropiedad/su:longitud ?longitud . }
                }";
        $result = $this->endpoint->query($string);

        $props = array();
        $props['id'] = $result->current()->id->getValue();
        $props['medi'] = $result->current()->medi->localName();
        if (isset($result->current()->latitud))
            $props['latitud'] = $result->current()->latitud->getValue();
        if (isset($result->current()->longitud))
            $props['longitud'] = $result->current()->longitud->getValue();
        $props['direccion'] = $result->current()->dir->getValue();
        $props['musica'] = $result->current()->musica->getValue();
        return $props;
    }

    function getNamesOfPlace($place_src)
    {
        $names = array();
        $result = $this->endpoint->query("
                SELECT ?nombreSitio ?base
                WHERE {
                    " . $place_src . " su:tienePropiedad ?prop .
                    ?prop su:nombreSitio ?nombreSitio .
                    ?prop su:provieneDeBD ?base .
                }"
        );
        foreach ($result as $name) {
            $temp_2 = array();
            $temp_2['nombre_sitio'] = $name->nombreSitio->getValue();
            $temp_2['proviene'] = $name->base->localName();
            array_push($names, $temp_2);
        }
        return $names;
    }

    function getImagesOfPlace($place_src)
    {
        $images = array();
        $result = $this->endpoint->query("
                SELECT ?imagen
                WHERE {
                    " . $place_src. " su:tienePropiedad ?prop .
                    ?prop su:imagenSitio ?imagen .
                }"
        );

        foreach ($result as $img) {
            $temp_3 = array();
            $temp_3['imagen'] = $img->imagen->getValue();
            array_push($images, $temp_3);
        }
        return $images;
    }

    function getCategoriesOfPlace($place_src)
    {
        $cats = array();
        $result = $this->endpoint->query("
                SELECT ?categoria ?proviene
                WHERE {
                    " . $place_src . " su:tienePropiedad ?prop .
                    ?prop su:categoria ?prop1 .
                    ?prop1 rdf:type ?categoria .
                    ?categoria rdfs:subClassOf [ rdf:rest* [ owl:onProperty su:esParteDeBD ; owl:allValuesFrom ?proviene ] ]
                    FILTER NOT EXISTS { 
                        ?prop1 a ?otra .
                        ?otra rdfs:subClassOf ?categoria .
                        FILTER(?otra != ?categoria)
                    }
                }"
        );
        foreach ($result as $cat) {
            $temp_3 = array();
            $temp_3['categoria'] = $cat->categoria->localName();
            $temp_3['proviene'] = $cat->proviene->localName();
            array_push($cats, $temp_3);
        }
        return $cats;
    }

    function getRatingsOfPlace($place_src)
    {
        $ratings = array();
        $result = $this->endpoint->query("
                SELECT ?calificacion ?prop ?base
                WHERE {
                    " . $place_src . " su:tienePropiedad ?prop .
                    ?prop su:calificacion ?calificacion .
                    ?prop su:provieneDeBD ?base .
                }"
        );
        foreach ($result as $rating) {
            $temp_3 = array();
            $temp_3['id'] = $this->getIdFromURI($rating->prop->getUri(), "prop_");
            $temp_3['calificacion'] = $rating->calificacion->getValue();
            $temp_3['proviene'] = $rating->base->localName();
            array_push($ratings, $temp_3);
        }

       $as = "OPTIONAL { 
        ?usuario su:visito ?v .
        ?v su:sitioVisitado ?sujeto .
        ?usuario su:idUsuario 768178 .
    }";
        return $ratings;
    }

    function getRatingsTotal($ratings)
    {
        $total = 0.0;
        if ($ratings) {
            foreach ($ratings as $rating) {
                if ($rating['proviene'] == "GooglePlaces")
                    $total += $rating['calificacion'] * 2.0;
                else
                    $total += $rating['calificacion'];
            }
            $total = $total / 2.0;
        }
        return $total;
    }

    function getCommentsOfPlace($place_src)
    {
        $comments = array();
        $result = $this->endpoint->query("
                SELECT ?comentario ?prop ?base
                WHERE {
                    " . $place_src. " su:tieneComentario ?prop .
                    ?prop su:comentario ?comentario .
                    ?prop su:provieneDeBD ?base .
                }"
        );
        foreach ($result as $comment) {
            $id = $this->getIdFromURI($comment->prop->getUri(), "comm_");
            $temp_3 = array();
            $temp_3['id'] = $id;
            $temp_3['comentario'] = $comment->comentario->getValue();
            $temp_3['proviene'] = $comment->base->localName();
            array_push($comments, $temp_3);
        }

        return $comments;

    }

    function getCommentOfPlaceFromUser($place_src, $id_user = null)
    {
        $comments = array();
        $query = "
            SELECT ?id ?usuario ?correo ?blank_c ?comentario 
            WHERE {
                ?u su:sitioVisitado " . $place_src . " .
                ?user su:visito ?u .
                ?u su:dejaComentario ?blank_c .
                ?blank_c su:conComentario ?comentario .
                ?user su:idUsuario ?id .
                ?user su:email ?correo .
                ?user su:usuario ?usuario . }";
        /*if ($id_user != null) {
            $query .= ".\nFILTER (?id != " . $id_user . ") } ";
        } else {
            $query .= ".\n } ";
        }*/
        $result = $this->endpoint->query($query);
        foreach ($result as $comment) {
            $id = $this->getIdFromURI($comment->blank_c->getUri(), "r_user_comment_");
            $temp_4 = array();
            $temp_5 = array();

            $temp_4['id'] = $id;
            $temp_4['comentario'] = $comment->comentario->getValue();

            $temp_5['id'] = $comment->id->getValue();
            $temp_5['usuario'] = $comment->usuario->getValue();
            $temp_5['email'] = $comment->correo->getValue();

            $temp_4['user'] = $temp_5;
            array_push($comments, $temp_4);
        }

        return $comments;
    }

    function stringWhereQuery($id, $idPlace) 
    {
        $query = "?u su:idUsuario " . strval($id) . " .
                ?u su:visito ?r .
                ?r su:sitioVisitado ?s .
                ?s su:idSitio " . strval($idPlace) . " .
                ?r ?p ?o .
                OPTIONAL {
                    ?o ?p1 ?o1 .
                    FILTER(isBlank(?o))
                }";
        return $query;
    }

    function reverse_strrchr($haystack, $needle, $trail) 
    {
        return strrpos($haystack, $needle) ? substr($haystack, 0, strrpos($haystack, $needle) + $trail) : false;
    }

}
