Bugs to fix. Mark as "Done: true" when fixed.

# Clear chat
- Done: false
- When clicking the "Clear chat" button, everything is deleted. Instead it should be called "New conversation" with a more descriptive icon. It should start a brand new conversation instead of clearing it, since there is logic to a new conversation starting (such as the initial message being customizable)

# Site content index not saving
- Done: false 
- Site Content Index doesn't update the checkbox selection. If I disselect "Posts" and rebuild the index, and refresh the page, the "posts" are still selected
- Unsure if the index is built correctly again whitout posts or not
- Rename the "Rebuild Content Index" to "Update Content Index"
- On button click (aka save) it should also save the checkboxes state


# Simplify search indexes UI

- Done: false
- in the AI chat box settings, in the search index section, we are showing two sections: one for the products index and one for the site content index. The site content index has different checkboxes for different parts of the content that we want to index. Let's merge those two sections into one, so that there is only one button to rebuild the index, and we're going to move the products into a checkbox. If the products are not selected, then we don't generate the products index. If you select it, we generate it. Same for all the other checkboxes


# Update the cart in the frontend when adding products via the chatbot UI
- Done: false 
- When adding a new product to the cart via the chat box UI (i.e., when the user clicks on Add to Cart), the products are correctly added to the cart. So, if we refresh the page, they're going to show up in the cart correctly.

But usually all WooCommerce pages will have a little cart icon that shows the amount of products in the cart. This is not updated when we add new products. Investigate if there is a way to do this or if there is some limitation or if we would need to do something drastic like refresh the page or something like that, which we don't want to do. 