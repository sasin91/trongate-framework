<?php

final readonly class Result
{
    public mixed $response;
    public Opcode $opcode;

    public function __construct(
        mixed $response,
    )
    {
        $this->response = $response;

        $this->opcode = Opcode::infer($response);
    }

    public function frame(): Frame
    {
        return Frame::encode(
            $this->response,
            $this->opcode
        );
    }
}
