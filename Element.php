<?php

namespace attitude\Elements;

final class API_Element
{
    private $request   = null;
    private $handler   = null;
    private $responder = null;

    public function __construct(Request_Element $request)
    {
        $this->setRequest($request);

        $handler = $this->request->getRequestURIArray();
        $handler = array_shift($handler);

        if (strlen($handler)===0) {
            $handler = DependencyContainer::get(get_called_class().'.DEFAULT_HANDLER', 'index');
        }

        try {
            $handler = DependencyContainer::get(get_called_class().'.handler('.$handler.')');
            $this->setHandler($handler);
        } catch (HTTPException $e) {
            trigger_error($e->getMessage());
            throw new HTTPException(400, "Cannot handle `/{$handler}`.");
        }

        // Try to load an accept
        foreach ($this->request->getAccept() as $accept) {
            $coeficient = 1.0;
            // Ignore coeficient for now; Takes order as presented in the header: 1st than 2nd, etc.
            if (strstr($accept, ';')) {
                list($accept, $coeficient) = explode(';', $accept);

                $coeficient = (float) $coeficient;
            }

            try {
                $responder = DependencyContainer::get(get_called_class().'.'.$accept.'_responder');

                break; // Found
            } catch (HTTPException $e) {
                trigger_error('Responder for accept is not set:'. get_called_class().'.'.$accept.'_responder');

                continue;
            }

            $this->setResponder($responder);
        }

        if ($this->responder===null) {
            $this->setResponder(DependencyContainer::get(get_called_class().'.responder'));
        }

        return $this;
    }

    public function setRequest(Request_Element $dependency)
    {
        $this->request = $dependency;

        return $this;
    }

    public function setHandler(Handler_Interface $dependency)
    {
        $this->handler = $dependency;
        $this->handler->setRequest($this->request);

        return $this;
    }

    public function setResponder(Responder_Interface $dependency)
    {
        $this->responder = $dependency;

        return $this;
    }

    public function respond()
    {
        try {
            $data = $this->handler->handle();
        } catch (HTTPException $e) {
            throw $e;
        }

        $this->responder->respond($data);

        return;
    }
}
