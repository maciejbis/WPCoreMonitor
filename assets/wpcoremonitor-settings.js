// Assuming you have included the wp.element and @wordpress/components libraries

document.addEventListener('DOMContentLoaded', function () {
    // Use the wp.element createElement and render functions
    const { createElement, render } = wp.element;

    // Use the wp.components Modal component
    const { Modal, Button } = wp.components;

    // Create the modal content component
    const ModalContent = () => {
        return createElement(
            'div',
            {},
            createElement('textarea', { disabled: true, class: "widefat", rows: 20 }, 'This textarea is disabled')
        );
    };

    // Function to handle button click and open the modal
    const handleButtonClick = () => {
        // Create a container element for the modal
        const modalContainer = document.createElement('div');
        document.body.appendChild(modalContainer);

        // Render the modal using wp.components Modal component
        render(
            createElement(
                Modal,
                {
                    title: 'My Modal',
                    onRequestClose: () => {
                        render(null, modalContainer);
                        document.body.removeChild(modalContainer);
                    },
                    size: 'large'
                },
                createElement(ModalContent)
            ),
            modalContainer
        );
    };

    // Get the button element
    const openModalLink = document.getElementById('openModalLink');

    // Attach the click event listener to the button
    openModalLink.addEventListener('click', handleButtonClick);
});
