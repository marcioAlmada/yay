FROM golang:1.10.1-alpine3.7 as builder

WORKDIR /go/src/nubank/authorizer

RUN apk --update add git openssh && \
    rm -rf /var/lib/apt/lists/* && \
    rm /var/cache/apk/*

RUN go get -u github.com/golang/dep/cmd/dep

COPY . .

RUN dep ensure

RUN go test -v ./...

RUN go build -ldflags "-s -w" -o ./authorize

FROM alpine:3.7

WORKDIR /app

RUN apk add --no-cache ca-certificates

COPY --from=builder /go/src/nubank/authorizer/authorize /usr/local/bin/

CMD authorize
