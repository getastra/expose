<?php

namespace Expose\Notify;

class Email extends \Expose\Notify
{
    /**
     * To email address for notifications
     * @var string
     */
    private $toAddress = null;

    /**
     * From address for notifications
     * @var string
     */
    private $fromAddress = 'notify@expose';

    /**
     * Init the object and set to/from addresses if given
     * 
     * @param string $toAddress "To" email address
     * @param string $fromAddress "From" email address
     */
    public function __construct($toAddress = null, $fromAddress = null)
    {
        if ($toAddress !== null) {
            $this->setToAddress($toAddress);
        }
        if ($fromAddress !== null) {
            $this->setFromAddress($fromAddress);
        }
    }

    /**
     * Set the "To" address for the notification
     *
     * @param string $emailAddress Email address
     */
    public function setToAddress($emailAddress)
    {
        if (filter_var($emailAddress, FILTER_VALIDATE_EMAIL) !== $emailAddress) {
            throw new \InvalidArgumentException('Invalid email address: '.$emailAddress);
        }
        $this->toAddress = $emailAddress;
    }

    /**
     * Get the current "To" address for notification
     *
     * @return string Email address
     */
    public function getToAddress()
    {
        return $this->toAddress;
    }

    /**
     * Set the current "From" email address on notifications
     * 
     * @param string $emailAddress Email address
     */
    public function setFromAddress($emailAddress)
    {
        if (filter_var($emailAddress, FILTER_VALIDATE_EMAIL) !== $emailAddress) {
            throw new \InvalidArgumentException('Invalid email address: '.$emailAddress);
        }
        $this->fromAddress = $emailAddress;
    }

    /**
     * Return the current "From" address setting
     * 
     * @return string Email address
     */
    public function getFromAddress()
    {
        return $this->fromAddress;
    }

    /**
     * Send the notification to the given email address
     * 
     * @param array $filterMatches Set of filter matches from execution
     * @return boolean Success/fail of sending email
     */
    public function send($filterMatches)
    {
        $toAddress = $this->getToAddress();
        $fromAddress = $this->getFromAddress();

        if ($toAddress === null) {
            throw new \InvalidArgumentException('Invalid "to" email address');
        }

        if ($fromAddress === null) {
            throw new \InvalidArgumentException('Invalid "from" email address');
        }

        //$loader = new \Twig_Loader_Filesystem(__DIR__.'/../Template');
        //$twig = new \Twig_Environment($loader);
        //$template = $twig->loadTemplate('Notify/Email.twig');

        $headers = array(
            "From: ".$fromAddress,
            "Content-type: text/html; charset=iso-8859-1"
        );
        $totalImpact = 0;

        $impactData = array();
        foreach ($filterMatches as $match) {
            $impactData[] = array(
                'impact' => $match->getImpact(),
                'description' => $match->getDescription(),
                'id' => $match->getId(),
                'tags' => implode(', ', $match->getTags())
            );
            $totalImpact += $match->getImpact();
        }

        $subject = 'Expose Notification - Impact Score '.$totalImpact;
        // $body = $template->render(array(
        //     'impactData' => $impactData,
        //     'runTime' => date('r'),
        //     'totalImpact' => $totalImpact
        // ));

        $date = date('r');
        $body2 = "<html>
        <body>
            Expose executed at {$date} and found the following matches in the submitted data:
            <br/><br/>
            <table cellspacing='0' cellpadding='3' border='0'>
            <tr><td><b>Impact</b></td><td><b>Description</b></td></tr>";
        
        foreach($impactData as $impact){
            $body2 .= "<tr><td align='center'>$impact[impact]</td><td>
            ($impact[description]) ($impact[id])</td></tr>
            <tr><td>&nbsp;</td><td><b>Tags:</b>$impact[tags]</td></tr>
            <tr><td>&nbsp;</td><td><b>Impact:</b>$impact[impact]</td></tr>
            <tr><td colspan='2'>&nbsp;</td></tr>";
        }
        $body2 .= "</table>
                <p>
                    <b>Total Impact Score:</b> $totalImpact
                </p>
            </body>
        </html>";

        return mail($toAddress, $subject, $body2, implode("\r\n", $headers));;
    }
}
