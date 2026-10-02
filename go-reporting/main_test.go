package main

import (
	"net/http/httptest"
	"testing"
)

func TestNumberParameterUsesDefault(t *testing.T) {
	request := httptest.NewRequest("GET", "/reports/top-products", nil)
	value, err := numberParameter(request, "limit", 10, 1, 100)

	if err != nil || value != 10 {
		t.Fatalf("expected default value 10, got %d and error %v", value, err)
	}
}

func TestNumberParameterRejectsValueOutsideRange(t *testing.T) {
	request := httptest.NewRequest("GET", "/reports/top-products?limit=101", nil)
	_, err := numberParameter(request, "limit", 10, 1, 100)

	if err == nil {
		t.Fatal("expected an error for limit 101")
	}
}

func TestMoneyRoundsToTwoDecimalPlaces(t *testing.T) {
	if result := money(12.345); result != 12.35 {
		t.Fatalf("expected 12.35, got %v", result)
	}
}
