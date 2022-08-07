<?php
/**
* Support Team Demand
*
* @package    WHMCS
* @author     Márcio Dias <contato@marcio-dias.com>
* @link       https://abale.com.br
* @version    1.0
**/

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

$arrayNoYes = array(
    0 => 'No',
    1 => 'Yes'
);

$arrayCharColors = array();
$arrayCharColors[] = "#1abc9c";
$arrayCharColors[] = "#16a085";
$arrayCharColors[] = "#2ecc71";
$arrayCharColors[] = "#27ae60";
$arrayCharColors[] = "#3498db";
$arrayCharColors[] = "#2980b9";
$arrayCharColors[] = "#9b59b6";
$arrayCharColors[] = "#8e44ad";
$arrayCharColors[] = "#34495e";
$arrayCharColors[] = "#2c3e50";

$arrayStatusColor = array();
$arrayStatusColor[0] = "#46A546";
$arrayStatusColor[1] = "#f1c40f";
$arrayStatusColor[2] = "#F89406";
$arrayStatusColor[3] = "#e74c3c";

$total_tickets = 0;
$footer_text_html = "";

$sql_activeclients = "";

if (isset($_REQUEST['onlyactiveclients']) && $_REQUEST['onlyactiveclients'] == 1) {
    $sql_activeclients = " AND customer.status='Active'";
}

$total_customers_db = Capsule::connection()->selectOne("SELECT count(DISTINCT(customer.id)) AS total  FROM tbltickets ticket INNER JOIN tblclients customer ON ticket.userid=customer.id WHERE YEAR(ticket.date) = '" . ($year) . "' ".$sql_activeclients." ");
$total_tickets_db = Capsule::connection()->selectOne("SELECT count(DISTINCT(ticket.id)) AS total  FROM tbltickets ticket INNER JOIN tblclients customer ON ticket.userid=customer.id WHERE YEAR(ticket.date) = '" . ($year) . "' ".$sql_activeclients."  ");
$total_avg_customer_tickets = ($total_tickets_db->total / 12) / $total_customers_db->total;

$result = Capsule::connection()->select("SELECT customer.companyname AS company, CONCAT(customer.firstname, ' ', customer.lastname) AS customer, customer.id AS customer_id, count(ticket.id) AS tickets, customer.status AS client_status FROM tbltickets ticket INNER JOIN tblclients customer ON ticket.userid=customer.id WHERE YEAR(ticket.date) = " . $year . " ".$sql_activeclients." GROUP BY customer.id ORDER BY tickets DESC");

$chartdata['cols'] = array();
$chartdata['cols'][] = array('label'=>'Customer','type'=>'string');
$chartdata['cols'][] = array('label'=>"Tickets",'type'=>'number');

$arrayDataInfo = array();
$arrayDataInfo[0] = array();
$arrayDataInfo[1] = array();
$arrayDataInfo[2] = array();
$arrayDataInfo[3] = array();

if (is_array($result) && count($result) > 0) {
    $total_revenue_db = Capsule::connection()->selectOne("SELECT SUM(Invoices.total) AS revenue FROM tblinvoices Invoices INNER JOIN tblclients Clients ON Invoices.userid = Clients.id WHERE Invoices.status = 'Paid'  AND Clients.currency='".(int)$currencyid."' AND YEAR(Invoices.datepaid) = '" . $year . "' GROUP BY YEAR(Invoices.datepaid) ");
    $total_revenue = $total_revenue_db->revenue;

    foreach ($result as $data) {
        $beforeRow = "";
        $afterRow = "";
        $customer = $data->company;
        $revenue_in = 0.00;
        $status_count = 0;
        
        $invoices_data = Capsule::connection()->selectOne("SELECT SUM(Invoices.total) AS revenue FROM tblinvoices Invoices INNER JOIN tblclients Clients ON Invoices.userid = Clients.id WHERE Invoices.status = 'Paid'  AND Clients.currency='".(int)$currencyid."' AND Clients.id='".(int)$data->customer_id."' AND YEAR(Invoices.datepaid) = '" . $year . "' GROUP BY Clients.id ");
        $previous_year_data = Capsule::connection()->selectOne("SELECT count(ticket.id) AS tickets FROM tbltickets ticket INNER JOIN tblclients customer ON ticket.userid=customer.id WHERE YEAR(ticket.date) = '" . ($year - 1) . "' AND customer.id='".(int)$data->customer_id."'  GROUP BY customer.id");
        
        $previous_year_tickets = intval($previous_year_data->tickets);
        $this_year_tickets = intval($data->tickets);

        if (empty($data->company)) {
            $customer = $data->customer;
        }

        if (intval($invoices_data->revenue) > 0) {
            $revenue_in = $invoices_data->revenue;
        }

        $revenue_divided_by_month = $revenue_in / 12;
        
        $parcial = $revenue_in / $data->tickets;

        $total_tickets += $data->tickets;

        $support_tickets_divided_by_revenue = formatCurrency($parcial);

        if (($revenue_divided_by_month) >= ($parcial)) {
            $status_count++;
        }

        $avg_tickets_mounth = number_format(($data->tickets / 12), 2);
        if (($avg_tickets_mounth) > (($total_avg_customer_tickets))) {
            $avg_tickets_mounth_html = "<span style='color:red;'>".$avg_tickets_mounth."</span>";
            $status_count++;
        } else {
            $avg_tickets_mounth_html = "<span>".$avg_tickets_mounth."</span>";
        }

        $customer_html = "<a href='clientssummary.php?userid=".$data->customer_id."'>".$customer."</a>";

        if ($data->client_status != "Active") {
            $beforeRow = "<span style='opacity:0.40;user-select: none;'>";
            $afterRow = "</span>";
        }

        if ($this_year_tickets < 0) {
            $this_year_tickets = 1;
        }

        if ($previous_year_tickets < 0) {
            $previous_year_tickets = 1;
        }

        $ticket_diff = ($this_year_tickets - $previous_year_tickets);
        $ticket_diff_percent = (($ticket_diff / $previous_year_tickets) * 100);

        $arrow_html = "";

        if ($previous_year_tickets > 0 && $this_year_tickets != $previous_year_tickets) {
            $arrow_html .= "<a title='Previous year: ".$previous_year_tickets."'>";

            if ($ticket_diff_percent > 0) {
                $status_count++;
                $arrow_color = "red";
                $arrow_html .= "<span style='margin-left:5px;width: 0;height: 0;border-left: 5px solid transparent;display:inline-block;border-right: 5px solid transparent;border-bottom: 5px solid ".$arrow_color.";'></span>";
            } else {
                $arrow_color = "green";
                $arrow_html .= "<span style='margin-left:5px;width: 0;height: 0;border-left: 5px solid transparent;display:inline-block;border-right: 5px solid transparent;border-top: 5px solid ".$arrow_color.";'></span>";
            }

            $arrow_html .= " <small style='color:".$arrow_color."'>".number_format($ticket_diff_percent)."%</small>";
            $arrow_html .= "</a>";
        }

        if ($status_count == 2) {
            $status_count++;
        }
         
        $status_html = "<span style='width: 10px;height: 10px;display: inline-block;border-radius: 50%;background-color:".$arrayStatusColor[$status_count].";'></span>";
        $tickets_html = "<a href='supporttickets.php?view=any&client=".$data->customer_id."'>".$data->tickets."</a>";
        $revenue_html = "<a href='clientsinvoices.php?userid=".$data->customer_id."'>".formatCurrency($revenue_in)."</a>";

        $revenue_percentage = number_format($revenue_in * 100 / $total_revenue, 2)."%";

        $arrayDataInfo[$status_count][] = array(
            $status_html,
            $beforeRow.$customer_html.$afterRow,
            $beforeRow.$revenue_html.$afterRow,
            $beforeRow.$revenue_percentage.$afterRow,
            $beforeRow.$avg_tickets_mounth_html.$afterRow,
            $beforeRow.$tickets_html.$arrow_html.$afterRow,
        );

        $rowsData = array();
        $rowsData[] = array('v'=> $customer);
        $rowsData[] = array('v'=> intval($data->tickets), 'f' => "Tickets");

        $chartdata['rows'][] = array(
            'c' => $rowsData
        );
    }

    $footer_text_html .= "<p align='center'><strong>Customers who Opened a Ticket:</strong> ".$total_customers_db->total." | <strong>Avg. Tickets per User per Month:</strong> ".number_format($total_avg_customer_tickets, 2)." | <strong>Total Tickets:</strong> " . number_format($total_tickets, 0, ".", ".") . '</p>';
}

$footer_text_html .= '*The "revenue percentage" shows how much revenue that customer represents compared to the total revenue from all customers over that period, including those who did not open a support ticket.';

$onlyactiveclients_alt = 1;

if (empty($_REQUEST['onlyactiveclients'])) {
    $onlyactiveclients = 0;
} else {
    $onlyactiveclients_alt = 0;
}

$showinactiveclients_link = "?report=".$_REQUEST['report']."&year=".$_REQUEST['year']."&onlyactiveclients=".$onlyactiveclients_alt;

$html_after_chart = "<div class='clearfix hidden-print'><div class='clientsummaryactions'>Show inactive customers: <a href='".$showinactiveclients_link."' style='text-decoration:underline;cursor:pointer'><strong class='textred'>".$arrayNoYes[$onlyactiveclients_alt]."</strong></a></div></div>";

$args = array();
$args['title'] = 'Tickets Submitted by Client';
$args['colors'] = implode(',', $arrayCharColors);
$args['chartarea'] = '80,20,90%,350';
$args['legendpos'] = 'right';

$reportdata["title"] = "Support team demand";
$reportdata["description"] = "This report attempts to single out customers who require a lot of effort from the support team and do not generate equivalent gains.";
$reportdata["yearspagination"] = true;
$reportdata["headertext"] = $chart->drawChart('Pie', $chartdata, $args, '400px').$html_after_chart;
$reportdata["tableheadings"] = array(
    'status' => "",
    'company' => 'Company',
    'revenue' => 'Total Revenue',
    'revenue-percentage' => 'Revenue %*',
    'amount-ticket' => 'Avg. Support Tickets per Month',
    'tickets' => 'Tickets Submitted',
);

$reportdata['footertext'] = $footer_text_html;
$reportdata["tablevalues"] = array_merge($arrayDataInfo[3], $arrayDataInfo[2], $arrayDataInfo[1], $arrayDataInfo[0]);
