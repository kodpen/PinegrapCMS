---
name: component-overrides-variants
description: Named variants of a shared component (CO5): props._variants on the component's root, props._variant on a place, applying one on the site, canvas and Preview, editing one in the designer
module: component_overrides
---

## Where is a variant kept?
- On the component's root, `props._variants`: a list of `{name, nodes}` in the order added; `nodes` maps a node's `_id` to the props the variant gives it. A place keeps only the name, `props._variant` on its placing `shared_ref`. No table or column.
