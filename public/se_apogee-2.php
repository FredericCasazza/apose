<?php
/**
 * ApoSE se_apogee-2.php
 * php version 7
 *
 * @category Education
 * @package  Apose
 * @author   2014 - CRI Université Lille 2 <cri@univ-lille.fr>
 * @author   2021-2024 - UniCA DSI <dsi.sen@univ-cotedazur.fr>
 * @author   2022 - Université Toulouse 1 Capitole <dsi@univ-tlse1.fr>
 * @license  GNU GPL
 * @link     https://github.com/suipnice/apose
 */
session_start();
if (isset($_SESSION["authen"]) === false or $_SESSION["authen"] !== 'ok') {
    session_destroy();
    echo '<meta http-equiv="Refresh" content="0;url=index.php">';
} else {
    include "../include/fonctions.php";
    include "../include/header.php";
    $cnx_mysql = connexionMysql();

    $pdf = "";
    $res = "";
    $res2 = "";
    $adresse = "";
    $cpt = 0;
    $entetes = [];

    if (empty($_POST) === false) {
        $RefEtp = filter_input(INPUT_POST, "RefEtp");
        $type = filter_input(INPUT_POST, "type");
        $numero = filter_input(INPUT_POST, "numero");
        $treeView = getPostBool("treeView");
        $_SESSION['treeView'] = $treeView;
        $ladd = filter_input(INPUT_POST, "ladd");
        $charge = filter_input(INPUT_POST, "charge");
        $cod_ses = getPostInt("cod_ses");
        $_SESSION['cod_ses'] = $cod_ses;
        $aff_epr = getPostInt("epr");
        $_SESSION['epr'] = $aff_epr;
        $cod_anu = filter_input(INPUT_POST, "cod_anu");
        $cycle = filter_input(INPUT_POST, "cycle");

        if ($RefEtp !== null && strpos($RefEtp, "|") !== false) {
            [$cod_etp_cible, $cod_vrs_vet, $comp, $cod_anu, $cycle] = explode(
                "|",
                $RefEtp
            );
        }

        if ($type === "webip") {
            $pdf = 0;
            $nom_fic = CHEMIN_PUBLIC . "pdf/" . $cod_etp_cible . ".csv";
            $fic = fopen($nom_fic, "w");
        }

        $_SESSION['cod_etp_cible'] = $cod_etp_cible;
        $_SESSION['cod_vrs_vet'] = $cod_vrs_vet;

        $fetched = mysqli_fetch_assoc(
            requete(
                $cnx_mysql,
                "SELECT *
                FROM etape
                WHERE cod_etp='$cod_etp_cible'
                    AND cod_vrs_vet='$cod_vrs_vet'"
            )
        );

        $lib_etp = $fetched['lib_etp'];
        $res2 .= "";
        $res2 .= "
        <p>État de la modélisation APOGEE :
            <strong>$cod_etp_cible $cod_vrs_vet</strong> --
            <strong>$lib_etp</strong></p>";
        if ($aff_epr === 1) {
            $res2 .= '<div class="columns">';
            $res2 .= '<div class="column">';
            if ($cod_ses === 0) {
                $res2 .= 'Épreuves : <strong>Session Unique</strong>';
            } else if ($cod_ses === 4) {
                $res2 .= 'Épreuves : <strong>toutes sessions</strong>';
            } else {
                $res2 .= "Épreuves : <strong>Session $cod_ses</strong>";
            }

            $res2 .= "</div><div class='column'>";
            $res2 .= '<p>Légende :</p><ul>';
            if ($cod_ses === 1 || $cod_ses === 4) {
                $res2 .= '<li class="sess-1">Épreuves SESSION 1</li>';
            }
            if ($cod_ses === 2 || $cod_ses === 4) {
                $res2 .= '<li class="sess-2">Épreuves SESSION 2</li>';
            }
            if ($cod_ses === 0 || $cod_ses === 4) {
                $res2 .= '<li class="sess-0">Épreuves SESSION Unique</li>';
            }
            $res2 .= '</ul>';
            $res2 .= "</div></div>";
        }//end if aff_epr == 1

        $t_etp_lse = etpLse($cnx_mysql, $cod_etp_cible, $cod_vrs_vet);

        $res2 .= "";

        $res_tablo[] = [
            "niveau",
            "cod_lse",
            "cod_elp",
            "cod_nel",
            "nbr_crd_elp",
            "lib_elp",
            "vol_hor",
            "coeff",
            "cod_elp_regroupe",
            "nb_fils",
            "lib_liste_filles"
        ];
        $libcharge = "";
        if ($charge === '1') {
            $resentetes = mysqli_query(
                $cnx_mysql,
                "SELECT type_heure.COD_TYP_HEU, type_heure.NUM_ORD_TYP_HEU
                 FROM type_heure;"
            );

            $nbchg = mysqli_num_rows($resentetes);
            if ($nbchg > 0) {
                $index = 0;
                while (
                    is_array($rowchg = mysqli_fetch_array($resentetes)) === true
                ) {
                    $libcharge .= "<th scope='col'>Charges Ens<br>
                        " . $rowchg['COD_TYP_HEU'] . "</th>";
                    $entetes[$index] = $rowchg['COD_TYP_HEU'];
                    $index += 1;
                }
            }
        } else {
            $libcharge = "";
            $nbchg = 0;
        }//end if charge == 1

        foreach ($t_etp_lse as $key => $cod_lse) {
            $niveau = 1;
            $req = requete(
                $cnx_mysql,
                "SELECT liste_elp.cod_lse, liste_elp.lib_lse,
                    vet_regroupe_lse.nbr_max_elp_obl_chx,
                    vet_regroupe_lse.nbr_min_elp_obl_chx
                 FROM vet_regroupe_lse,liste_elp
                 WHERE vet_regroupe_lse.cod_lse = liste_elp.cod_lse
                 AND liste_elp.cod_lse='$cod_lse'"
            );
            while (is_array($row = mysqli_fetch_row($req)) === true) {
                $lib_liste = $row[1];
                $max = $row[2];
                $min = $row[3];
                if (empty($max) === false) {
                    $lib_liste .= " (Liste à choix $max $min)";
                }
            }

            if ($type === "tableau") {
                $res2 .= '<div class="has-text-right">';
                $res2 .= '<a class="button" id="copyBtn">';
                $res2 .= getIconText("clipboard", "Copier le tableau");
                $res2 .= '</a></div>';
                $res2 .= "<table id='arbo2'
                    class='table is-striped is-hoverable is-fullwidth'>";
                $res2 .= "<caption>$cod_lse&nbsp;: $lib_liste</caption>";
                if ($aff_epr === 1) {
                    $libses = "<br>/ Session";
                } else {
                    $libses = "";
                }
                if (SYLLABUS_LINK !== "") {
                    $code_title = "Code (et lien syllabus)";
                } else {
                    $code_title = "Code";
                }
                $res2 .= "<thead><tr>
                            <th scope='col'>Libellé</th>
                            <th scope='col'>$code_title</th>
                            <th scope='col'>Nature</th>
                            <th scope='col'>Période</th>
                            <th scope='col'>ECTS$libses</th>
                            <th scope='col'>Nb IP</th>
                            " . $libcharge . "
                            <th scope='col'>Code liste</th>
                            <th scope='col'>Observations</th>
                            <th scope='col'>Commentaires</th>
                        </tr></thead>";
            } else {
                $res2 .= "<p>$cod_lse&nbsp;: $lib_liste</p>";
            }

            $res_tmp = chercheElpFils(
                $cnx_mysql,
                $nbchg,
                $entetes,
                $cod_lse,
                $niveau,
                $type,
                $numero,
                $treeView,
                $res_tablo
            );
            if ($type === "webip") {
                $res_tablo = $res_tmp;
            } else {
                $res2 .= $res_tmp;
            }

            if ($type === "tableau") {
                $res2 .= "</table>";
            }
        }
    }//end if $_POST
    ?>

    <div class="container">
        <div class="content p-2">
            <form method="post" action="se_apogee.php?#cyc<?php echo $cycle; ?>">
                <input type="hidden" name="Liste_Comp" value="<?php echo $comp; ?>">
                <input type="hidden" name="cod_anu" value="<?php echo $cod_anu; ?>">
                <input type="hidden" name="numero" value="<?php echo $numero; ?>">
                <input type="hidden" name="treeView" value="<?php echo $treeView; ?>">
                <input type="hidden" name="ladd" value="<?php echo $ladd; ?>">
                <input type="hidden" name="charge" value="<?php echo $charge; ?>">
                <input class="button is-primary is-light"
                    type="submit" value="Retour">
            </form>

            <?php
            // Affichage du libellé de la composante.
            $reqa = requete(
                $cnx_mysql,
                "SELECT lib_cmp FROM composante WHERE cod_cmp='$comp'"
            );
            while (is_array($row = mysqli_fetch_row($reqa)) === true) {
                $lib_comp = $row[0];
            }
            ?>

            <h1 class="has-text-centered mt-2">
                Consultation de la structure des enseignements :</h1>
            <h2 class="has-text-centered">
                <?php echo $lib_comp; ?> | <?php echo $cod_anu; ?>
            </h2>

            <?php
            echo $res2;
            ?>

            <form method="post" action="se_apogee.php?#cyc<?php echo $cycle; ?>">
                <input type="hidden" name="Liste_Comp" value="<?php echo $comp; ?>">
                <input type="hidden" name="cod_anu" value="<?php echo $cod_anu; ?>">
                <input type="hidden" name="numero" value="<?php echo $numero; ?>">
                <input type="hidden" name="treeView" value="<?php echo $treeView; ?>">
                <input type="hidden" name="ladd" value="<?php echo $ladd; ?>">
                <input type="hidden" name="charge" value="<?php echo $charge; ?>">
                <input class="button is-primary is-light"
                    type="submit" value="Retour">
            </form>
        </div>
    </div>
    <?php
    include "../include/footer.php";
}
?>