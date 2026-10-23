<?php
/**
 * ApoSE fonctions.php
 * php version 7
 *
 * @category Education
 * @package  Apose
 * @author   2014 - CRI Université Lille 2 <cri@univ-lille.fr>
 * @author   2021-2025 - UniCA DSI <dsi.sen@univ-cotedazur.fr>
 * @author   2022 - Université Toulouse 1 Capitole <dsi@univ-tlse1.fr>
 * @license  GNU GPL
 * @link     https://github.com/suipnice/apose
 */

require __DIR__ . '/../vendor/autoload.php';
require "../param.php";


/**
 * Get param from POST and ensure it's an int, with specified default value
 *
 * @param string $param   The param to get
 * @param int    $default The default value
 *
 * @return int the param value
 */
function getPostInt($param, $default=0): int
{
    $def = ['options' => ['default' => $default]];
    return filter_input(INPUT_POST, $param, FILTER_VALIDATE_INT, $def);
}


/**
 * Get param from POST and ensure it's a bool, with specified default value
 *
 * @param string $param   The param to get
 * @param bool   $default The default value
 *
 * @return bool the param value
 */
function getPostBool($param, $default=false): bool
{
    $def = ['options' => ['default' => $default]];
    return filter_input(INPUT_POST, $param, FILTER_VALIDATE_BOOLEAN, $def);
}


/**
 * Affiche à l'utilisateur le message associé à une exception
 *
 * @param mixed $message   Un message générique
 * @param mixed $exception L'exception déclenchée
 *
 * @return void
 */
function printException($message, $exception)
{
    if (php_sapi_name() !== 'cli') {
        include_once "../include/header.php";
        echo "<div class=\"container mt-6\">";
        echo "<div class=\"notification is-danger\">";
        echo "$message : <pre>" . htmlspecialchars($exception->getMessage());
        echo "</pre></div></div>";
        include_once "../include/footer.php";
    }
    error_log("$message: " . $exception->getMessage());
}


/**
 * Authentification auprès d'un serveur CAS + LDAP
 *
 * @return string Affiliation principale de l’utilisateur
 */
function authentificationCAS(): string
{
    // Import des paramètres du serveur CAS.
    global $connexionCAS;
    global $logoutCas;

    // Initialisation phpCAS.
    $phpCAS = new phpCAS();
    if ($connexionCAS !== "active") {
        $phpCAS->client(
            CAS_VERSION_3_0,
            CAS_HOST,
            CAS_PORT,
            CAS_URI,
            SERVICE_BASE_URL
        );
        $connexionCAS = "active";
    }

    if ($logoutCas === 1) {
        $phpCAS->logout();
    }

    // Redirection vers la page d'authentification de CAS.
    $phpCAS->setNoCasServerValidation();
    try {
        $phpCAS->forceAuthentication();
    } catch (CAS_AuthenticationException $e) {
        // Gestion de l'erreur d'authentification CAS.
        printException("Échec de l’authentification CAS", $e);
        exit;
    }

    // L'utilisateur a été correctement identifié.
    $usernameCAS = $phpCAS->getUser();

    // On lance l'identification LDAP avec le UID de la personne.
    $statut = identificationLDAP($usernameCAS);

    return $statut;

} //end authentification_CAS()


/**
 * Identification auprès d’un serveur LDAP
 * et fournit le champ edupersonprimaryaffiliation
 *
 * @param mixed $login UID
 *
 * @return string Affiliation principale de la personne connectée
 */
function identificationLDAP($login)
{

    $baseDN = LDAP_BASE_DN;
    $ldapServer = LDAP_SERVEUR;

    // Mot clef de la recherche.
    $keyword = $login;

    // Connexion au serveur.
    $conn = ldap_connect($ldapServer);

    /*
     * 2ème étape : on effectue une liaison au serveur, ici de type "anonyme"
     * pour une recherche permise par un accès en lecture seule
     */

    // On dit qu'on utilise LDAP V3, sinon la V2 par défaut est utilisé
    // et le bind ne passe pas.
    ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);

    // Instruction de liaison.
    // Connexion Manager.
    ldap_bind($conn, LDAP_BIND_RDN, LDAP_BIND_PWD);

    // 3ème étape : on effectue une recherche avec le dn de base.
    $query = "(&(uid=" . $keyword . "))";
    $result = ldap_search($conn, $baseDN, $query);

    if ($result === false) {
        return "";
    }

    // Lecture du resultat.
    $info = ldap_get_entries($conn, $result);

    $prim_affiliation = "";

    for ($i = 0; $i < $info["count"]; $i++) {
        $uid = $info[$i]["uid"][0];
        if (isset($info[$i]["supannetuid"]) === true) {
            $supannetuid = $info[$i]["supannetuid"][0];
        } else {
            $supannetuid = "";
        }
        $mail = $info[$i]["mail"][0];
        $username = $info[$i]["cn"][0];
        $prim_affiliation = $info[$i]["edupersonprimaryaffiliation"][0];

        $_SESSION['login'] = $uid;
        $_SESSION['supannetuid'] = $supannetuid;
        $_SESSION['mail'] = $mail;
        $_SESSION['username'] = $username;
        $_SESSION['statut'] = $prim_affiliation;
    }

    // Cloture de la session LDAP.
    ldap_close($conn);

    return $prim_affiliation;

}


/**
 * Connexion à une base MySQL
 *
 * @param mixed $base_mysql   Nom de la base Mysql
 * @param mixed $hote_mysql   Serveur Mysql
 * @param mixed $user_mysql   Utilisateur Mysql
 * @param mixed $passwd_mysql Mdp Mysql
 *
 * @return mysqli instanciated mysqli object
 */
function connexionMysql(
    $base_mysql = MYSQL_BASE_DATAS,
    $hote_mysql = HOTE_MYSQL,
    $user_mysql = USER_MYSQL,
    $passwd_mysql = PASSWD_MYSQL
) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $link = new mysqli($hote_mysql, $user_mysql, $passwd_mysql, $base_mysql);
    } catch (mysqli_sql_exception $e) {
        printException("Échec de la connexion BDD", $e);
        exit;
    }
    return $link;
}


/**
 * Performs a query on the database
 *
 * @param mysqli $cnx_mysql Instanciated mysqli class
 * @param mixed  $libreq    Requete SQL à executer
 * @param int    $debug     Mode debug
 * @param string $mode      "multi" for multiple requests on a single query
 *
 * @return mysqli_result
 */
function requete($cnx_mysql, $libreq, $debug = 0, $mode = "")
{
    debug($libreq);
    if ($mode == "multi") {
        $req = mysqli_multi_query($cnx_mysql, $libreq);

        if ($req === TRUE) {
            // Comptage du nombre de lignes insérées
            $totalInserted = 0;
            do {
                if ($result = mysqli_store_result($cnx_mysql)) {
                    // SELECT, ignore
                    mysqli_free_result($result);
                } else {
                    // Pour INSERT, UPDATE, DELETE
                    $affected = mysqli_affected_rows($cnx_mysql);
                    if ($affected > 0) {
                        $totalInserted += $affected;
                    } else {
                        reportError($cnx_mysql, $libreq);
                    }
                }
            } while (mysqli_more_results($cnx_mysql) && mysqli_next_result($cnx_mysql));
            printlog("Nombre de lignes insérées/affectées : $totalInserted");

            // Check for MySQL warnings after each query
            $warnings = $cnx_mysql->query("SHOW WARNINGS");
            if ($warnings && $warnings->num_rows > 0) {
                printlog("MySQL Warnings detected:");
                while ($row = $warnings->fetch_assoc()) {
                    echo implode(" | ", $row) . "\n";
                }
                die("Script interrompu à cause d'un warning MySQL.\n");
            }
        } else {
            reportError($cnx_mysql, $libreq);
        }
    } else {
        $req = mysqli_query($cnx_mysql, $libreq);
    }
    if ($debug !== 0) {
        echo $libreq . '<br><br>';
    }

    if ($req !== false) {
        return $req;
    }
    reportError($cnx_mysql, $libreq);
}

/**
 * Arrete le sript et affiche un détail de l'erreur.
 *
 * @param mixed $cnx_mysql
 * @param mixed $libreq
 * @return never
 */
function reportError($cnx_mysql, $libreq){
    $erreur = "\nErreur requête\n";
    // Pour le debug.
    $erreur .= $libreq . "\n" . MYSQLI_ERROR($cnx_mysql) . "\n";

    echo $erreur;
    die("UNE ERREUR A ÉTÉ RENCONTRÉE\n\n");
}


/**
 * Recuperation des listes d’elp pour une version d’etape
 *
 * @param mysqli $cnx_mysql     Instanciated mysqli class
 * @param mixed  $cod_etp_cible Code étape cible
 * @param mixed  $cod_vrs_vet   CODE VRS VET
 *
 * @return array listes d'elp
 */
function etpLse($cnx_mysql, $cod_etp_cible, $cod_vrs_vet)
{
    $reqetp_lse = "SELECT *
        FROM vet_regroupe_lse, liste_elp
        WHERE cod_etp='$cod_etp_cible'
            AND cod_vrs_vet='$cod_vrs_vet'
            AND vet_regroupe_lse.cod_lse=liste_elp.cod_lse";
    debug("Récuperation des listes elp pour une version etape.");
    $req = requete($cnx_mysql, $reqetp_lse);
    $res = [];
    while (is_array($fetched = mysqli_fetch_assoc($req)) === true) {
        $res[] = $fetched['cod_lse'];
    }
    return $res;

}

/**
 * Génere une chaine de caracteres permettant d'afficher un niveau d'arbre en ASCII
 *
 * @param int  $niveau   Niveau actuel dans l'arbre
 * @param bool $treeView Affichage de l'arbre
 *
 * @return string
 */
function getTabulation($niveau, $treeView=true): string
{
    $tabulation = "<span class='treeview niv_$niveau'>";
    if ($treeView) {
        for ($i = 2; $i < $niveau; $i++) {
            $tabulation .= "│&nbsp;&nbsp;";
        }
        if ($niveau > 1) {
            $tabulation .= "├─ ";
        }
    } else {
        for ($i = 1; $i < $niveau; $i++) {
            $tabulation .= "&nbsp;&nbsp;";
        }
    }
    $tabulation .= "</span>";
    return $tabulation;
}

/**
 * Recuperation des elp fils d'une liste d'une version d'etape
 *
 * @param mysqli $cnx_mysql Instanciated mysqli class
 * @param mixed  $nbchg     nbchg
 * @param mixed  $entetes   entetes
 * @param mixed  $cod_lse   cod_lse
 * @param mixed  $niveau    niveau
 * @param string $type      'Tableau' ou '?'
 * @param mixed  $numero    Affichage des numéros hierarchiques
 * @param mixed  $treeView  Affichage visuel de la hierarchie
 * @param mixed  $res_tablo res_tablo
 *
 * @return mixed elp fils
 */
function chercheElpFils(
    $cnx_mysql,
    $nbchg,
    $entetes,
    $cod_lse,
    $niveau,
    $type = "tableau",
    $numero = 0,
    $treeView = 1,
    $res_tablo = []
) {
    // GLOBAL $apogee;
    $res = "";
    $cod1 = "";
    $cod2 = "";
    // Libelle Annexe Descriptive du Diplome.
    $ladd = filter_input(INPUT_POST, 'ladd');
    // Charge d'enseignement.
    $charge = filter_input(INPUT_POST, 'charge');
    // Affichage epreuve.
    // $epr = filter_input(INPUT_POST, "epr");
    // Affichage session.
    // $ses = filter_input(INPUT_POST, "cod_ses");

    $label_sess = [
        0 => "Unique",
        1 => "1",
        2 => "2",
    ];

    if ($type === "tableau") {
        $tabulation1 = getTabulation($niveau, $treeView);
        $tag = "td";
    } else {
        $tag = "span";
        $tabulation1 = "";
        $res .= "<ul type='none'>";
        $cod1 = "[";
        $cod2 = "]";
    }

    $etp = $_SESSION['cod_etp_cible'];
    $cod_vrs_vet = $_SESSION['cod_vrs_vet'];
    $cod_anu = filter_input(INPUT_POST, 'cod_anu');

    $req = requete(
        $cnx_mysql,
        "SELECT lse_regroupe_elp.cod_elp, table_elp.lib_elp,
            table_elp.cod_nel, table_elp.cod_pel, table_elp.nbr_crd_elp,
            table_elp_nbetu.nb_etu_ip,
            table_elp.lib_elp_lng,
            table_elp_nbetu.cod_etp,
            table_elp.lib_cmt_elp
        FROM table_elp
        INNER JOIN lse_regroupe_elp
            ON table_elp.cod_elp = lse_regroupe_elp.cod_elp
        LEFT OUTER JOIN table_elp_nbetu
            ON table_elp.cod_elp = table_elp_nbetu.cod_elp
            AND table_elp_nbetu.cod_anu = '$cod_anu'
            AND table_elp_nbetu.cod_etp = '$etp'
            AND table_elp_nbetu.cod_vrs_etp = '$cod_vrs_vet'
        WHERE lse_regroupe_elp.cod_lse = '$cod_lse'
        ORDER BY table_elp.lib_elp"
    );
    $i = 0;

    while (is_array($fetched = mysqli_fetch_assoc($req)) === true) {
        $cod_elp = $fetched['cod_elp'];
        $cod_nel = $fetched['cod_nel'];
        $cod_pel = $fetched['cod_pel'];
        $nbr_crd_elp = $fetched['nbr_crd_elp'];

        // Recuperation nb IP de l'elp.
        $reqnbip = requete(
            $cnx_mysql,
            "SELECT table_elp_nbetu.nb_etu_ip
             FROM table_elp_nbetu
             WHERE table_elp_nbetu.cod_elp = '$cod_elp'
                AND table_elp_nbetu.cod_etp = '$etp'
                AND table_elp_nbetu.cod_vrs_etp = '$cod_vrs_vet'"
        );
        $elp_nbetu = "";
        while (is_array($rnbip = mysqli_fetch_assoc($reqnbip)) === true) {
            $elp_nbetu = $rnbip['nb_etu_ip'];
        }

        // avec lib ADD
        if ($ladd === "1") {
            $i++;
            // Ne faire apparaitre l'ADD que pour les élements SEM et UE
            if ($cod_nel === "UE" or $cod_nel === "SE") {
                $lib_elp = $fetched['lib_elp'] . "<br>
                    <span class='lib-add'>" . $fetched['lib_elp_lng'] . "</span>";
            } else {
                $lib_elp = $fetched['lib_elp'];
            }
        } else { // sans ADD
            $i++;
            $lib_elp = $fetched['lib_elp'];
        } //Fin if ladd

        if ($type === "tableau") {
            $res .= "<tr rel='$cod_elp'><td>";
        } else {
            $res .= "<li>";
        }
        // voir si fils
        $req2 = requete(
            $cnx_mysql,
            "SELECT t1.cod_lse, t2.cod_typ_lse, t1.nbr_min_elp_obl_chx,
                t1.nbr_max_elp_obl_chx, t2.lib_lse
            FROM elp_regroupe_lse AS t1
            INNER JOIN liste_elp AS t2
                ON t1.cod_lse=t2.cod_lse
            WHERE cod_elp='$cod_elp'"
        );
        $nb_fils = mysqli_num_rows($req2);
        if ($nb_fils > 0) {
            $desc = 1;
            $tag1 = '<strong class="apo-parent">';
            $tag2 = "</strong>";
        } else {
            $desc = 0;
            $tag1 = "";
            $tag2 = "";
        }

        if ($numero) {
            $lib_niveau = "$niveau.$i";
        } else {
            $lib_niveau = "";
        }
        if ($type === "tableau") {
            $affcharge = '';
            if ($charge === "1") {
                // Recup infos Charge.
                $sql = "SELECT DISTINCT COD_TYP_HEU,
                               NB_HEU_ELP
                     FROM elp_chg_typ_heu
                     WHERE cod_anu = '$cod_anu'
                       AND cod_elp = '$cod_elp'
                     ORDER BY COD_TYP_HEU;";
                debug($sql);
                $qcharg = $cnx_mysql->query($sql);
                if ($qcharg->num_rows === 0) {
                    for ($n = 0; $n < $nbchg; $n++) {
                        $affcharge .= "<$tag class='no-charge'></$tag>";
                    }
                } else {
                    $index = 0;

                    while (
                        is_array($rcharg = mysqli_fetch_array($qcharg)) === true
                    ) {

                        while (
                            strcmp($rcharg['COD_TYP_HEU'], $entetes[$index]) != 0
                        ) {
                            $affcharge .= "<$tag></$tag>";
                            $index++;
                        }

                        $affcharge .= "<$tag rel='nb_heu'>
                            " . $rcharg['NB_HEU_ELP'] . "</$tag>";
                        $index++;

                    }
                    for (; $index < $nbchg; $index++) {
                        $affcharge .= "<$tag></$tag>";
                    }
                }
            }

            if ($desc === 0 and SYLLABUS_LINK !== "") {
                $link = str_replace("[[cod_elp]]", $cod_elp, SYLLABUS_LINK);
                $link = str_replace("[[cod_anu]]", $cod_anu, $link);
                $aff_cod_elp = "<a href='$link' target=\"_blank\"
                    title='Voir le syllabus de $cod_elp (nouvel onglet)'>
                    $cod1$cod_elp$cod2</a>";
            } else {
                $aff_cod_elp = "$cod1$cod_elp$cod2";
            }

            $res .= "$tabulation1 $lib_niveau $tag1$lib_elp$tag2
                     <$tag rel='cod_elp'>$aff_cod_elp</$tag>
                     <$tag rel='cod_nel'>$cod_nel</$tag>
                     <$tag rel='cod_pel'>$cod_pel</$tag>
                     <$tag rel='nb_crd_elp'>$nbr_crd_elp</$tag>
                     <$tag rel='nbetu'>$elp_nbetu</$tag> $affcharge";
        } else {
            // Type is not Table
            $res .= "$tabulation1 $lib_niveau $tag1$lib_elp$tag2";
        }

        $lib_liste_filles = "";
        $t_liste_lse_filles = [];
        while (is_array($fetch2 = mysqli_fetch_assoc($req2)) === true) {
            $t_liste_lse_filles[] = $fetch2;
            $lib_liste_filles .= "[" . implode("|", $fetch2) . "]";
        }

        $cod_elp_regroupe = "";
        $res_tablo[] = [
            $niveau,
            $cod_lse,
            $cod_elp,
            $cod_nel,
            $nbr_crd_elp,
            $lib_elp,
            $cod_elp_regroupe,
            $nb_fils,
            $lib_liste_filles
        ];

        $lib_cmt = $fetched['lib_cmt_elp'];
        if (strpos($lib_cmt, "[") !== false) {
            $css_class = "tag is-warning";
        } else {
            $css_class = "lib-cmt lib-add";
        }
        $lib_cmt = "<$tag rel='cmt'>
            <span class='$css_class'>".$lib_cmt."</span>
            </$tag>";

        // desc = 1 si il y a des fils/filles.
        if ($desc === 1) {
            foreach ($t_liste_lse_filles as $key => $r2) {
                // TODO : aller à la ligne quand il y a plus d'une fille ?
                // Pour les elements fils suivants
                if ($key > 0) {
                    if ($type === "tableau") {
                        $res .= "<tr rel='".$r2['cod_lse']."' class='liste_lse'>";
                        $res .= "<td>";
                        $res .= "$tabulation1 $tag1$lib_niveau".$r2['lib_lse'].$tag2;
                        $res .= "</td><td></td><td>LISTE</td><td colspan='3'></td>";
                        if ($charge === "1") {
                            $res .= "<td colspan='3'></td>";
                        }
                    } else {
                        $res .= "<li rel='".$r2['cod_lse']."'>";
                    }
                }
                $max = $r2['nbr_max_elp_obl_chx'];
                $min = $r2['nbr_min_elp_obl_chx'];
                $cod_lse_aff = $r2['cod_lse'];
                if ($type === "tableau") {
                    $res .= "<$tag rel='cod_lse'><em>$cod_lse_aff</em></$tag>";
                }
                if ($max > 1) {
                    $pluriel = "s";
                } else {
                    $pluriel = "";
                }
                $card = "&nbsp;";
                if ($min and $min === $max) {
                    $card = " $max élément$pluriel à choisir";
                }
                if ($min and $min < $max) {
                    $card = " de $min à $max élément$pluriel à choisir";
                }
                $res .= "<$tag rel='obs'>$card</$tag>";

                $res .= $lib_cmt;
                if ($numero and $type <> "tableau") {
                    $res .= ":";
                }
                if ($type === "tableau") {
                    $res .= "</tr>";
                } else {
                    $res .= "</li>";
                }

                // AFFICHAGE SESSIONS paramètres :
                // 1=session 1 | 2=session 2
                // 3=session unique | 4=toutes les sessions
                if ($_SESSION['epr'] === 1) {
                    $code_ses = $_SESSION['cod_ses'];
                    if ($code_ses === 4) {
                        // Affichage de toutes les sessions.
                        $critsess = '';
                    } else {
                        $critsess = "and cod_ses='$code_ses'";
                    }

                    //Recherche d'epreuve pour l'element
                    $reqepr = mysqli_query(
                        $cnx_mysql,
                        "SELECT epreuve.cod_epr, epreuve.lib_epr, epreuve.cod_nep,
                                epreuve.cod_tep, epr_sanctionne_elp.cod_ses
                        FROM epr_sanctionne_elp, epreuve
                        WHERE epr_sanctionne_elp.cod_epr=epreuve.cod_epr
                        " . $critsess . "
                        AND epr_sanctionne_elp.cod_elp='" . $cod_elp . "'
                        ORDER BY epr_sanctionne_elp.cod_ses,
                                 epreuve.lib_epr, epreuve.cod_epr"
                    );
                    $tabulation2 = getTabulation($niveau+1, $treeView);
                    while (is_array($repr = mysqli_fetch_array($reqepr)) === true) {
                        $res .= "<tr class='sess-" . $repr[4] . "'>
                            <td>$tabulation2 $lib_niveau" . $repr[1] . "</td>
                            <td>" . $repr[0] . "</td>
                            <td>" . $repr[2] . "</td>
                            <td>" . $repr[3] . "</td>
                            <td>" . $repr[4] . "</td>";

                        if ($charge === "1") {
                            $res .= "<td></td><td></td><td></td>";
                        }
                        $res .= "<td></td><td></td><td></td>";
                        $res .= "</tr>";
                    }
                }//end if Affichage des sessions

                // Montage pdf-csv.
                if ($type === "webip") {
                    $res_tablo = chercheElpFils(
                        $cnx_mysql,
                        $nbchg,
                        $entetes,
                        $r2['cod_lse'],
                        $niveau + 1,
                        $type,
                        $numero,
                        $treeView,
                        $res_tablo
                    );
                } else {
                    $res .= chercheElpFils(
                        $cnx_mysql,
                        $nbchg,
                        $entetes,
                        $r2['cod_lse'],
                        $niveau + 1,
                        $type,
                        $numero,
                        $treeView
                    ) . "";
                }
            }
        } else {
            // desc = 0 ==> pas de fils/filles.
            if ($type === "tableau") {
                $res .= "<$tag>&nbsp;</$tag><$tag>&nbsp;</$tag>$lib_cmt</tr>";
            } else {
                $res .= "";
            }
            if ($_SESSION['epr'] === 1) {
                $cod_ses = $_SESSION['cod_ses'];
                if ($cod_ses === 4) {
                    //Affichage de toutes les sessions
                    $critsess = '';
                } else {
                    $critsess = "AND epr_sanctionne_elp.cod_ses='$cod_ses'";
                }
                //Recherche d'epreuve pour l'element
                $reqepr = mysqli_query(
                    $cnx_mysql,
                    "SELECT epreuve.cod_epr, epreuve.lib_epr, epreuve.cod_nep,
                            epreuve.cod_tep, epr_sanctionne_elp.cod_ses
                    FROM epr_sanctionne_elp, epreuve
                    WHERE epr_sanctionne_elp.cod_epr=epreuve.cod_epr
                    " . $critsess . "
                    AND epr_sanctionne_elp.cod_elp='" . $cod_elp . "'
                    ORDER BY epr_sanctionne_elp.cod_ses,
                             epreuve.lib_epr, epreuve.cod_epr"
                );

                $tabulation2 = getTabulation($niveau+1, $treeView);
                while ($repr = $reqepr->fetch_array()) {
                    $res .= "<tr class='sess-" . $repr[4] . "'>
                            <td rel='repr1'>$tabulation2 $lib_niveau
                            " . $repr[1] . "</td>
                            <td rel='repr0'>" . $repr[0] . "</td>
                            <td rel='repr2'>" . $repr[2] . "</td>
                            <td rel='repr3'>" . $repr[3] . "</td>
                            <td rel='repr4'>" . $label_sess[$repr[4]] . "</td>";
                    if ($charge === "1") {
                        $res .= "<td></td><td></td><td></td>";
                    }
                    $res .= "<td></td><td></td><td></td>";
                    $res .= "</tr>";
                }
            } //Fin If Affichage des sessions
        }

    }

    if ($type === "webip") {
        return $res_tablo;
    }
    if ($type === "tableau") {
        $res .= "";
    } else {
        $res .= "</ul>";
    }
    return $res;

}


/**
 * Print debug of a mixed value
 *
 * @param mixed $value The value to display
 *
 * @return void
 */
function debug($value)
{
    if (APP_MODE_DEBUG === "YES") {
        echo '<pre class="debug">';
        if ($value) {
            if (is_array($value) or is_object($value)) {
                print_r($value);
            } else {
                echo $value;
            }
        } else {
            echo "Aucun enregistrement";
        }
        echo "</pre>\n";
    }
}


/**
 * Get Bulma HTML code for icon + associated text
 *
 * @param string $icon Font awesome icon
 * @param string $text Text to be displayed
 *
 * @return string HTML formatted icon
 */
function getIconText($icon, $text)
{
    return '<span class="icon-text">
        <span class="icon">
        <i class="fas fa-' . $icon . '" aria-hidden="true"></i>
        </span>
        <span>' . $text . '</span>
    </span>';
}

/**
 * Print datetimed log
 *
 * @param mixed $log
 *
 * @return void
 */
function printlog($log) {
    $date = new DateTime();
    $date = $date->format("Y-m-d H:i:s");
    print("$date -- $log\n");
}


/**
 * Fonction similaire à trim(), mais remplace les espaces au début et à la fin par des underscores.
 * Remplace tous les espaces consécutifs au début par un '_', et à la fin par un '_'.
 *
 * @param string $string La chaîne à traiter.
 * @return string La chaîne avec les espaces au début et à la fin remplacés par '_'.
 */
function underscore_spaces($string) {
    $string = preg_replace('/^\s+/', '_', $string);
    $string = preg_replace('/\s+$/', '_', $string);
    return $string;
}
