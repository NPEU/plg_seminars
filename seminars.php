<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  CSVUploads.Seminars
 *
 * @copyright   Copyright (C) NPEU 2019.
 * @license     MIT License; see LICENSE.md
 */

defined('_JEXEC') or die;

/**
 * Generate PDF's from seminars data when CSV is uploaded.
 */
class plgCSVUploadsSeminars extends JPlugin
{
    protected $autoloadLanguage = true;
    protected $data;


    /**
     * Method to instantiate the indexer adapter.
     *
     * @param   object  &$subject  The object to observe.
     * @param   array   $config    An array that holds the plugin configuration.
     *
     */
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->loadLanguage();
    }

    /**
     * @param   array  $csv  Array holding data
     *
     * @return  mixed  Boolean true on success or String 'STOP'
     */
    public function onAfterLoadCSV($csv, $filename)
    {
        if ($filename != 'npeu-seminar-dates.csv') {
            return false;
        }

        $last_mod = date('Y-m-d-Hm');
        $data = array(
            'terms'   => array(),
            'lastmod' => $last_mod
        );
        foreach ($csv as  $row) {
            $term    = $row['Term'];
            $date    = strtotime($row['Date']);
            $seminar = array(
                'date'         => date('c', $date),
                'start'        => $row['Time Start'],
                'end'          => $row['Time End'],
                'speaker'      => $row['Speaker'],
                'speaker_role' => $row['Speaker Role'],
                'title'        => $row['Title'],
                'location'     => $row['Location'],
                'notes'        => $row['Notes'],
                'cancelled'    => $row['Cancelled'],
                'term'         => $term,
                'lastmod'      => $last_mod
            );

            // Create encoded string for iCal service:
            $event_date  = date('Y-m-d', $date);
            $start       = strtotime($event_date . ' ' . $seminar['start']);
            $end         = strtotime($event_date . ' ' . $seminar['end']);
            $description = 'NPEU Seminar Series ' . $term . "\n\n*" . $seminar['speaker'] . "*\n(" . $seminar['speaker_role'] . ")\n\n_" . $seminar['title'] . "_\n\nAll welcome.";
            if (!empty($seminar['notes'])) {
                $description .= "\n\n" . $seminar['notes'];
            }
            $location = $seminar['location'];
            $summary  = 'NPEU Seminar: ' . $seminar['speaker'];

            $cal_data = array(
                'alias'       => 'npeu-seminar-' . $event_date,
                'description' => htmlspecialchars($description),
                'start'       => $start,
                'end'         => $end,
                'location'    => $location,
                'summary'     => $summary
            );

            $seminar['event_code'] = urlencode(base64_encode(json_encode($cal_data)));
            $data['terms'][$term][] = $seminar;
        }

        $this->data = $data;
        return true;
    }
    
    
    /**
     * @param   string  $json  JSON string
     *
     * @return  boolean  True on success
     */
    public function onBeforeSaveJSON($json, $filename)
    {
        if ($filename != 'npeu-seminar-dates.csv') {
            return false;
        }
        
        // Update the JSON:
        $json = json_encode($this->data);
        
        // Write PDF versions:
        $seminars_dir = '../../../downloads/files/npeu/seminars/';
        $template     = $seminars_dir . 'Seminars Template.pdf';
        require_once('../../../libs/tcpdf/tcpdf.php');
        require_once('../../../libs/fpdi/fpdi.php');
        foreach ($this->data['terms'] as $term => $seminars) {
            $pdf = new FPDI();

            $pdf->SetMargins(15, 50, 15);
            $pdf->setFontSubsetting(true);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);


            $pdf->SetTextColor(0, 0, 0);

            $pdf->setSourceFile($seminars_dir . "Seminars Template.pdf");
            $tpl_idx = $pdf->importPage(1);

            $pdf->addPage();
            $pdf->useTemplate($tpl_idx, 0, 0);

            $pdf->SetFont('calibri', 'B', 24);
            $h1 = '<h1 style="text-align: center;">NPEU Seminar Series<br />' . $term . '<br /></h1>';
            $pdf->writeHTMLCell(0, 0, '', '', $h1, 0, 1, 0, true, '', true);

            $pdf->SetFont('arial', '', 11);

            $pdf->setFontSubsetting(false);

            $dates  = '<table>';
            $dates .= '<tr><td colspan="2"><hr /></td></tr>';

            foreach ($seminars as $seminar) {

                if ($seminar['cancelled'] == 'Y') {
                    $dates .= '<tr><td colspan="2" style="text-align: center"><i><b>Please note:</b> the following seminar has been <b>CANCELLED</b>:</i><br /></td></tr>';
                }

                $dates .= '<tr' . ($seminar['cancelled'] == 'Y' ? ' style="color: #777"' : '') .'>';

                $dates .= '<td width="20%"><b style="font-family: arialbd; font-size: 13pt;">' . date('F', strtotime($seminar['date'])) . '</b><br />' . date('D j\<\s\u\p\>S\<\/\s\u\p\>', strtotime($seminar['date'])) . '<br /><br />' . $seminar['start'] .' – ' . $seminar['end'] . '</td>';
                $dates .= '<td width="80%"><b style="font-family: arialbd; font-size: 13pt;">' . $seminar['speaker'] . '</b><br /><i style="font-size: 10pt; font-family: ariali;">' . $seminar['speaker_role'] . '</i><br />' . $seminar['title'];

                if ($seminar['location'] != 'Richard Doll Lecture Theatre') {
                    $dates .= '<br /><b style="font-family: arialbd;">' . $seminar['location'] . '</b>';
                }
                if (!empty($seminar['notes'])) {
                    $dates .= '<br />' . $seminar['notes'];
                }
                $dates .= '</td>';
                $dates .= '</tr>';
                $dates .= '<tr><td colspan="2"><br /><hr /></td></tr>';

            }

            $dates .= '</table>';
            $pdf->writeHTMLCell(0, 0, '', '', $dates, 0, 1, 0, true, '', true);

            $pdf->Output($seminars_dir . 'NPEU Seminars - ' . $term . ' ' . $last_mod . '.pdf', 'F');
        }
        
        return true;
    }
}