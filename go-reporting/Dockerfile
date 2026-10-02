FROM golang:1.24-alpine AS builder

WORKDIR /app
COPY go.mod go.sum ./
RUN go mod download

COPY . .
RUN go test ./...
RUN CGO_ENABLED=0 go build -o reporting-service .

FROM alpine:3.21
WORKDIR /app
COPY --from=builder /app/reporting-service .

EXPOSE 8080
CMD ["./reporting-service"]
