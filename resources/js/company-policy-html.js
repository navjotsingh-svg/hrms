const MARKUP_START = /^\s*(<!doctype\b|<html\b|<head\b|<body\b|<style\b|<header\b|<div\b|<h1\b|<section\b|<article\b|<main\b|<nav\b)/i;

const hasCustomStyle = (html) => /<style[\s>]/i.test(String(html || ''));

const unwrapEscapedPolicyHtml = (html) => {
    const source = String(html || '').trim();

    if (!source) {
        return '';
    }

    const probe = document.createElement('div');
    probe.innerHTML = source;
    const visible = (probe.textContent || '').replace(/^\uFEFF/, '').trim();
    const tagCount = (visible.match(/<\/?[a-z][\w:-]*(?:\s[^<>]*)?>/gi) || []).length;

    if ((tagCount >= 2 && MARKUP_START.test(visible)) || tagCount >= 8) {
        return visible;
    }

    return source;
};

export { hasCustomStyle, unwrapEscapedPolicyHtml };
