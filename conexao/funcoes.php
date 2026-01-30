<?php
// Fetch proposals
$sql = "SELECT p.* FROM propostas p ORDER BY p.data_emissao DESC";
$stmt = $pdo->query($sql);
$propostas = $stmt->fetchAll();

function calcular_totais(PDO $pdo, $id_proposta) {
    // soma preços das áreas
    $sql = "SELECT IFNULL(SUM(preco_sem_iva),0) AS soma_areas, IFNULL(SUM(preco_sem_iva * taxa_iva/100),0) AS iva_areas
            FROM areas_proposta WHERE id_proposta = ?";
    $st = $pdo->prepare($sql);
    $st->execute([$id_proposta]);
    $r = $st->fetch();

    $soma_areas = (float)$r['soma_areas'];
    $iva_areas = (float)$r['iva_areas'];

    // levantamentos
    $sql2 = "SELECT preco_levantamento_arquitetonico, preco_levantamento_topografico, preco_levantamento_drone
             FROM propostas WHERE id = ?";
    $st2 = $pdo->prepare($sql2);
    $st2->execute([$id_proposta]);
    $lev = $st2->fetch();

    $lev_total = 0.0;
    if (!empty($lev)) {
        $lev_total += (float)$lev['preco_levantamento_arquitetonico'];
        $lev_total += (float)$lev['preco_levantamento_topografico'];
        $lev_total += (float)$lev['preco_levantamento_drone'];
    }

    $gross_excl = $soma_areas + $lev_total;

    // desconto
    $sql3 = "SELECT desconto_percentagem FROM propostas WHERE id = ?";
    $st3 = $pdo->prepare($sql3);
    $st3->execute([$id_proposta]);
    $drow = $st3->fetch();
    $desconto_pct = $drow ? (float)$drow['desconto_percentagem'] : 0.0;
    $desconto_val = round($gross_excl * ($desconto_pct/100.0), 2);

    $net_excl = round($gross_excl - $desconto_val, 2);

    // IVA: calculado por áreas (após desconto proporcional) + VAT aproximado nos levantamentos (vamos assumir 23% para levantamentos se existirem)
    $iva_total = 0.0;
    if ($gross_excl > 0) {
        // calcular partilha das áreas para distribuir desconto
        $sqlAreas = "SELECT preco_sem_iva, taxa_iva FROM areas_proposta WHERE id_proposta = ?";
        $stA = $pdo->prepare($sqlAreas);
        $stA->execute([$id_proposta]);
        $areas = $stA->fetchAll();
        foreach ($areas as $a) {
            $area_preco = (float)$a['preco_sem_iva'];
            $taxa = (float)$a['taxa_iva'];
            $share = $gross_excl > 0 ? ($area_preco / $gross_excl) : 0;
            $area_base = max(0, $area_preco - ($desconto_val * $share));
            $iva_total += round($area_base * ($taxa/100.0), 2);
        }
        // VAT for levantamentos (approx): apply 23% on levantamentos after discount proportion
        if ($lev_total > 0) {
            $lev_share = $lev_total / $gross_excl;
            $lev_base_after_disc = max(0, $lev_total - ($desconto_val * $lev_share));
            $iva_total += round($lev_base_after_disc * (23.0/100.0), 2);
        }
    }

    $total_incl = round($net_excl + $iva_total, 2);

    return [
        'gross_excl' => number_format($gross_excl,2,'.',''),
        'desconto_val' => number_format($desconto_val,2,'.',''),
        'net_excl' => number_format($net_excl,2,'.',''),
        'iva_total' => number_format($iva_total,2,'.',''),
        'total_incl' => number_format($total_incl,2,'.','')
    ];
}
?>