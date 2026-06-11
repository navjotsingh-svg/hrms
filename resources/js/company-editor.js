import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const EMPTY_EDITOR_VALUES = ['', '<p><br></p>', '<p></p>'];

export const isEmptyEditorContent = (html) => {
    if (!html) {
        return true;
    }

    const normalized = html.replace(/\s/g, '').toLowerCase();

    return EMPTY_EDITOR_VALUES.some((value) => normalized === value.replace(/\s/g, ''));
};

export const initCompanyDescriptionEditor = () => {
    const textarea = document.getElementById('description');
    const editorContainer = document.getElementById('descriptionEditor');

    if (!textarea || !editorContainer) {
        return null;
    }

    const quill = new Quill(editorContainer, {
        theme: 'snow',
        placeholder: 'Describe the company, its mission, services, and culture...',
        modules: {
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['link'],
                ['clean'],
            ],
        },
    });

    if (textarea.value && !isEmptyEditorContent(textarea.value)) {
        quill.root.innerHTML = textarea.value;
    }

    const syncEditorValue = () => {
        const html = quill.root.innerHTML;
        textarea.value = isEmptyEditorContent(html) ? '' : html;
    };

    quill.on('text-change', syncEditorValue);

    return {
        quill,
        sync: syncEditorValue,
    };
};
